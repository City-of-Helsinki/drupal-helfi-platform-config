<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_search\Kernel\Controller;

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\helfi_search\Controller\SearchController;
use Drupal\helfi_search\EmbeddingModel;
use Drupal\helfi_search\EmbeddingApiInterface;
use Drupal\helfi_search\QueryBuilder;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\helfi_platform_config\Kernel\KernelTestBase;
use Drupal\Tests\helfi_platform_config\Traits\ElasticKernelTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests search controller.
 */
#[RunTestsInSeparateProcesses]
#[Group('helfi_search')]
class SearchControllerTest extends KernelTestBase {

  use ProphecyTrait;
  use ApiTestTrait;
  use ElasticKernelTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'search_api',
    'helfi_search',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['system']);
    $this->installEntitySchema('user');
    $this->setUpCurrentUser(permissions: ['access content']);
  }

  /**
   * Tests flood protection.
   */
  public function testFloodProtection(): void {
    // Exhaust the flood limit.
    $flood = $this->container->get('flood');
    for ($i = 0; $i < SearchController::FLOOD_THRESHOLD; $i++) {
      $flood->register(SearchController::FLOOD_EVENT, SearchController::FLOOD_WINDOW);
    }

    $request = $this->getMockedRequest('/api/v1/search', parameters: ['q' => 'test query']);
    $response = $this->processRequest($request);

    $this->assertEquals(429, $response->getStatusCode());
  }

  /**
   * Tests that the page is validated before the embedding API is called.
   */
  public function testPageOutOfRange(): void {
    $embeddingsModel = $this->prophesize(EmbeddingApiInterface::class);
    $embeddingsModel->getEmbedding(Argument::cetera())->shouldNotBeCalled();
    $this->container->set(EmbeddingApiInterface::class, $embeddingsModel->reveal());

    $request = $this->getMockedRequest('/api/v1/search', parameters: [
      'q' => 'test query',
      'page' => '99999999999999999999',
    ]);
    $response = $this->processRequest($request);
    $this->assertEquals(400, $response->getStatusCode());

    // The page is out of range when it starts after the last KNN result.
    $request = $this->getMockedRequest('/api/v1/search', parameters: [
      'q' => 'test query',
      'page' => (string) (QueryBuilder::KNN_K / QueryBuilder::KNN_DEFAULT_SIZE + 2),
    ]);
    $response = $this->processRequest($request);
    $this->assertEquals(400, $response->getStatusCode());

    // The flood event is not registered for invalid requests.
    $this->assertTrue($this->container->get('flood')->isAllowed(SearchController::FLOOD_EVENT, 1, SearchController::FLOOD_WINDOW));
  }

  /**
   * Tests search endpoint.
   */
  public function testSearch(): void {
    $embeddingsModel = $this->prophesize(EmbeddingApiInterface::class);
    $embeddingsModel->getEmbedding(Argument::type('string'), Argument::type(EmbeddingModel::class))
      ->willReturn(array_fill(0, 3, 0.1));

    $this->container->set(EmbeddingApiInterface::class, $embeddingsModel->reveal());

    $this->mockEtusivuElasticClient([
      // Empty response (no promotions, no KNN results).
      $this->createElasticsearchResponse([
        'responses' => [
          ['hits' => ['hits' => []]],
          ['hits' => ['hits' => []]],
        ],
      ]),
      // Response with promoted and KNN results.
      $this->createElasticsearchResponse([
        'responses' => [
          [
            'hits' => [
              'hits' => [
                [
                  '_score' => 1.2,
                  '_source' => [
                    'title' => ['Promoted Result'],
                    'description' => ['A promoted description'],
                    'link' => ['/fi/promoted'],
                    'search_api_language' => ['fi'],
                  ],
                ],
              ],
            ],
          ],
          [
            'hits' => [
              'hits' => [
                [
                  '_score' => 0.95,
                  '_source' => [
                    'entity_type' => ['node'],
                    'url' => ['/fi/test-page'],
                    'label' => ['Test Page'],
                    'search_api_language' => ['fi'],
                  ],
                ],
              ],
            ],
          ],
          [
            'hits' => [
              'hits' => [
                [
                  '_score' => 3.1,
                  '_source' => [
                    'name_parts' => ['Matti', 'Meikäläinen'],
                    'email' => ['matti.meikalainen@hel.fi'],
                    'organization_hierarchy' => [
                      ['id' => 1, 'name' => 'Kaupunginkanslia'],
                      ['id' => 2, 'name' => 'Viestintäosasto'],
                    ],
                  ],
                ],
              ],
            ],
          ],
        ],
      ]),
      // Promotion sub-query errors, KNN succeeds.
      $this->createElasticsearchResponse([
        'responses' => [
          ['error' => ['type' => 'index_not_found_exception']],
          [
            'hits' => [
              'hits' => [
                [
                  '_score' => 0.80,
                  '_source' => [
                    'entity_type' => ['node'],
                    'url' => ['/fi/fallback'],
                    'label' => ['Fallback Page'],
                    'search_api_language' => ['fi'],
                  ],
                ],
              ],
            ],
          ],
        ],
      ]),
      // Error response.
      new Response(500, [], 'Internal Server Error'),
    ]);

    // Test empty results.
    $request = $this->getMockedRequest('/api/v1/search', parameters: ['q' => 'no results query']);
    $response = $this->processRequest($request);

    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode((string) $response->getContent(), TRUE);
    $this->assertEmpty($data['results']);
    $this->assertEmpty($data['promoted']);
    $this->assertEmpty($data['contacts']);

    // Test with promoted and KNN results.
    $request = $this->getMockedRequest('/api/v1/search', parameters: ['q' => 'test query']);
    $response = $this->processRequest($request);

    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode((string) $response->getContent(), TRUE);
    $this->assertCount(1, $data['promoted']);
    $this->assertEquals('Promoted Result', $data['promoted'][0]['title']);
    $this->assertEquals('A promoted description', $data['promoted'][0]['description']);
    $this->assertEquals('/fi/promoted', $data['promoted'][0]['url']);
    $this->assertCount(1, $data['results']);
    $this->assertEquals(0.95, $data['results'][0]['score']);
    $this->assertEquals('node', $data['results'][0]['entity_type']);
    $this->assertEquals('/fi/test-page', $data['results'][0]['url']);
    $this->assertEquals('Test Page', $data['results'][0]['title']);
    $this->assertCount(1, $data['contacts']);
    $this->assertEquals('Matti Meikäläinen', $data['contacts'][0]['name']);
    $this->assertEquals('matti.meikalainen@hel.fi', $data['contacts'][0]['email']);
    $this->assertEquals(['Kaupunginkanslia', 'Viestintäosasto'], $data['contacts'][0]['organization_hierarchy']);

    // Test promotion error is handled gracefully.
    $request = $this->getMockedRequest('/api/v1/search', parameters: ['q' => 'test query']);
    $response = $this->processRequest($request);

    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode((string) $response->getContent(), TRUE);
    $this->assertEmpty($data['promoted']);
    $this->assertCount(1, $data['results']);
    $this->assertEquals('/fi/fallback', $data['results'][0]['url']);
    $this->assertEmpty($data['contacts']);

    // Test total ES failure.
    $request = $this->getMockedRequest('/api/v1/search', parameters: ['q' => 'test query']);
    $response = $this->processRequest($request);

    $this->assertEquals(503, $response->getStatusCode());
  }

  /**
   * Tests the 'others' filter and the debug parameter.
   *
   * 'others' means all content except news, and it runs a single search.
   * The debug parameter adds result counts per content type.
   */
  public function testOthersBundleAndDebugAggregations(): void {
    $embeddingsModel = $this->prophesize(EmbeddingApiInterface::class);
    $embeddingsModel->getEmbedding(Argument::type('string'), Argument::type(EmbeddingModel::class))
      ->willReturn([0.1, 0.2, 0.3]);
    $this->container->set(EmbeddingApiInterface::class, $embeddingsModel->reveal());

    // Two single-search responses; both carry an aggs section so we can
    // assert downstream handling regardless of whether debug is honoured.
    $hits = [
      'hits' => [
        'total' => ['value' => 1],
        'hits' => [
          [
            '_score' => 0.9,
            '_source' => [
              'entity_type' => ['node'],
              'entity_bundle' => ['page'],
              'url' => ['/fi/p'],
              'label' => ['P'],
            ],
          ],
        ],
      ],
      'aggregations' => [
        'bundles' => [
          'buckets' => [
            ['key' => 'page', 'doc_count' => 4],
            ['key' => 'landing_page', 'doc_count' => 2],
          ],
        ],
      ],
    ];
    $this->mockEtusivuElasticClient([
      $this->createElasticsearchResponse($hits),
      $this->createElasticsearchResponse($hits),
    ]);

    // Users without the permission to tune the search get no debug payload.
    $request = $this->getMockedRequest('/api/v1/search', parameters: [
      'q' => 'test query',
      'bundle' => 'others',
      'debug' => '1',
    ]);
    $response = $this->processRequest($request);
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode((string) $response->getContent(), TRUE);
    $this->assertCount(1, $data['results']);
    $this->assertArrayNotHasKey('debug', $data);

    $this->setUpCurrentUser(permissions: ['access content', 'administer helfi_search']);

    // 1) ?bundle=others&debug=1 → debug payload returned.
    $request = $this->getMockedRequest('/api/v1/search', parameters: [
      'q' => 'test query',
      'bundle' => 'others',
      'debug' => '1',
    ]);
    $response = $this->processRequest($request);
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode((string) $response->getContent(), TRUE);
    $this->assertCount(1, $data['results']);
    // 'others' takes the single-search branch, so no promotions are queried.
    $this->assertEmpty($data['promoted']);
    $this->assertSame(['page' => 4, 'landing_page' => 2], $data['debug']['bundles']);
  }

  /**
   * Tests the 'contacts' filter on its own.
   *
   * Only contacts are searched, and they are paged like other results.
   * The AI embedding API is not called.
   */
  public function testContactsOnly(): void {
    $embeddingsModel = $this->prophesize(EmbeddingApiInterface::class);
    $embeddingsModel->getEmbedding(Argument::cetera())->shouldNotBeCalled();
    $this->container->set(EmbeddingApiInterface::class, $embeddingsModel->reveal());

    $transactions = [];
    $this->mockEtusivuElasticClient([
      $this->createElasticsearchResponse([
        'hits' => [
          'total' => ['value' => 23],
          'hits' => [
            ['_score' => 2.0, '_source' => ['name_parts' => ['Matti', 'Meikäläinen']]],
          ],
        ],
      ]),
    ], $transactions);

    $request = $this->getMockedRequest('/api/v1/search', parameters: [
      'q' => 'meikäläinen',
      'bundle' => 'contacts',
      'page' => '2',
    ]);
    $response = $this->processRequest($request);
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode((string) $response->getContent(), TRUE);
    $this->assertCount(1, $data['contacts']);
    $this->assertEquals('Matti Meikäläinen', $data['contacts'][0]['name']);
    $this->assertEquals(23, $data['contacts_total']);
    $this->assertEquals(23, $data['total_hits']);
    $this->assertEmpty($data['results']);

    /** @var array<int, array{request: \Psr\Http\Message\RequestInterface}> $transactions */
    $this->assertCount(1, $transactions);
    $body = json_decode((string) $transactions[0]['request']->getBody(), TRUE);
    $this->assertEquals(QueryBuilder::KNN_DEFAULT_SIZE, $body['from']);
    $this->assertEquals(QueryBuilder::KNN_DEFAULT_SIZE, $body['size']);

    // Contacts are never returned to anonymous users.
    $this->container->get('current_user')->setAccount(new AnonymousUserSession());
    $response = $this->processRequest($request);
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode((string) $response->getContent(), TRUE);
    $this->assertEmpty($data['contacts']);
    $this->assertEquals(0, $data['total_hits']);
    $this->assertCount(1, $transactions);
  }

  /**
   * Tests that content results are paged after the contacts.
   */
  public function testContactsWithContent(): void {
    $embeddingsModel = $this->prophesize(EmbeddingApiInterface::class);
    $embeddingsModel->getEmbedding(Argument::type('string'), Argument::type(EmbeddingModel::class))
      ->willReturn([0.1, 0.2, 0.3]);
    $this->container->set(EmbeddingApiInterface::class, $embeddingsModel->reveal());

    $transactions = [];
    $this->mockEtusivuElasticClient([
      // Contacts: 13 in total, the last 3 are on page 2.
      $this->createElasticsearchResponse([
        'hits' => [
          'total' => ['value' => 13],
          'hits' => array_fill(0, 3, ['_score' => 2.0, '_source' => ['name_parts' => ['Matti']]]),
        ],
      ]),
      // News results.
      $this->createElasticsearchResponse([
        'hits' => [
          'total' => ['value' => 40],
          'hits' => [
            ['_score' => 0.9, '_source' => ['url' => ['/fi/news'], 'label' => ['News']]],
          ],
        ],
      ]),
    ], $transactions);

    $request = $this->getMockedRequest('/api/v1/search', parameters: [
      'q' => 'test query',
      'bundle' => 'contacts,news',
      'page' => '2',
    ]);
    $response = $this->processRequest($request);
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode((string) $response->getContent(), TRUE);
    $this->assertCount(3, $data['contacts']);
    $this->assertEquals(13, $data['contacts_total']);
    $this->assertCount(1, $data['results']);
    $this->assertEquals(53, $data['total_hits']);

    /** @var array<int, array{request: \Psr\Http\Message\RequestInterface}> $transactions */
    $this->assertCount(2, $transactions);
    // Page 2 starts at the 11th item: contacts 11-13 and content items 1-7.
    $knnBody = json_decode((string) $transactions[1]['request']->getBody(), TRUE);
    $this->assertEquals(0, $knnBody['from']);
    $this->assertEquals(7, $knnBody['size']);
  }

  /**
   * Tests the 'news' sentinel expands to every configured news bundle.
   *
   * Captures the outgoing Elasticsearch request body and asserts the KNN
   * filter's entity_bundle terms clause contains both news_item and
   * news_article — the bug case was that only the literal value sent by
   * the React form (news_item) reached the filter.
   */
  public function testNewsSentinelExpandsToAllNewsBundles(): void {
    $embeddingsModel = $this->prophesize(EmbeddingApiInterface::class);
    $embeddingsModel->getEmbedding(Argument::type('string'), Argument::type(EmbeddingModel::class))
      ->willReturn([0.1, 0.2, 0.3]);
    $this->container->set(EmbeddingApiInterface::class, $embeddingsModel->reveal());

    $transactions = [];
    $this->mockEtusivuElasticClient([
      $this->createElasticsearchResponse([
        'hits' => ['total' => ['value' => 0], 'hits' => []],
      ]),
    ], $transactions);

    $request = $this->getMockedRequest('/api/v1/search', parameters: [
      'q' => 'test query',
      'bundle' => 'news',
    ]);
    $response = $this->processRequest($request);
    $this->assertEquals(200, $response->getStatusCode());

    /** @var array<int, array{request: \Psr\Http\Message\RequestInterface}> $transactions */
    $this->assertCount(1, $transactions);
    $body = json_decode((string) $transactions[0]['request']->getBody(), TRUE);

    // The bundle terms clause may sit alongside the language term clause
    // under bool.must — scan rather than index-by-position.
    $bundleClause = array_find(
      $body['knn']['filter']['bool']['must'],
      static fn (array $c): bool => isset($c['terms']['entity_bundle']),
    );
    $this->assertNotNull($bundleClause, 'KNN filter is missing the entity_bundle terms clause.');
    $this->assertEqualsCanonicalizing(
      ['news_item', 'news_article'],
      $bundleClause['terms']['entity_bundle'],
    );
  }

}
