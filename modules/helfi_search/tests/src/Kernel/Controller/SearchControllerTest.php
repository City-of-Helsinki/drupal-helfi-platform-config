<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_search\Kernel\Controller;

use Drupal\helfi_search\Controller\SearchController;
use Drupal\helfi_search\EmbeddingModel;
use Drupal\helfi_search\EmbeddingsModelInterface;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\helfi_platform_config\Kernel\KernelTestBase;
use Drupal\Tests\helfi_platform_config\Traits\ElasticTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Elastic\Elasticsearch\ClientBuilder;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
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
  use ElasticTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
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
   * Tests search endpoint.
   */
  public function testSearch(): void {
    $embeddingsModel = $this->prophesize(EmbeddingsModelInterface::class);
    $embeddingsModel->getEmbedding(Argument::type('string'), Argument::type(EmbeddingModel::class))
      ->willReturn(array_fill(0, 3, 0.1));

    $this->container->set(EmbeddingsModelInterface::class, $embeddingsModel->reveal());

    $client = ClientBuilder::create()
      ->setHttpClient($this->createMockHttpClient([
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
      ]))
      ->build();

    $this->container->set('helfi_platform_config.etusivu_elastic_client', $client);

    // Test empty results.
    $request = $this->getMockedRequest('/api/v1/search', parameters: ['q' => 'no results query']);
    $response = $this->processRequest($request);

    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode((string) $response->getContent(), TRUE);
    $this->assertEmpty($data['results']);
    $this->assertEmpty($data['promoted']);

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

    // Test promotion error is handled gracefully.
    $request = $this->getMockedRequest('/api/v1/search', parameters: ['q' => 'test query']);
    $response = $this->processRequest($request);

    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode((string) $response->getContent(), TRUE);
    $this->assertEmpty($data['promoted']);
    $this->assertCount(1, $data['results']);
    $this->assertEquals('/fi/fallback', $data['results'][0]['url']);

    // Test total ES failure.
    $request = $this->getMockedRequest('/api/v1/search', parameters: ['q' => 'test query']);
    $response = $this->processRequest($request);

    $this->assertEquals(503, $response->getStatusCode());
  }

  /**
   * Tests the 'others' sentinel and the debug query param.
   *
   * 'others' expands to "everything except news bundles" and routes through
   * single search() (not msearch). The debug param surfaces per-bundle aggs.
   */
  public function testOthersBundleAndDebugAggregations(): void {
    $embeddingsModel = $this->prophesize(EmbeddingsModelInterface::class);
    $embeddingsModel->getEmbedding(Argument::type('string'), Argument::type(EmbeddingModel::class))
      ->willReturn([0.1, 0.2, 0.3]);
    $this->container->set(EmbeddingsModelInterface::class, $embeddingsModel->reveal());

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
    $client = ClientBuilder::create()
      ->setHttpClient($this->createMockHttpClient([
        $this->createElasticsearchResponse($hits),
        $this->createElasticsearchResponse($hits),
      ]))
      ->build();
    $this->container->set('helfi_platform_config.etusivu_elastic_client', $client);

    // 1) ?bundle=others&debug=1 in a non-prod env → debug payload returned.
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
   * Tests the 'news' sentinel expands to every configured news bundle.
   *
   * Captures the outgoing Elasticsearch request body and asserts the KNN
   * filter's entity_bundle terms clause contains both news_item and
   * news_article — the bug case was that only the literal value sent by
   * the React form (news_item) reached the filter.
   */
  public function testNewsSentinelExpandsToAllNewsBundles(): void {
    $embeddingsModel = $this->prophesize(EmbeddingsModelInterface::class);
    $embeddingsModel->getEmbedding(Argument::type('string'), Argument::type(EmbeddingModel::class))
      ->willReturn([0.1, 0.2, 0.3]);
    $this->container->set(EmbeddingsModelInterface::class, $embeddingsModel->reveal());

    $transactions = [];
    $mock = new MockHandler([
      $this->createElasticsearchResponse([
        'hits' => ['total' => ['value' => 0], 'hits' => []],
      ]),
    ]);
    $stack = HandlerStack::create($mock);
    $stack->push(Middleware::history($transactions));
    $elasticClient = ClientBuilder::create()
      ->setHttpClient(new Client(['handler' => $stack]))
      ->build();
    $this->container->set('helfi_platform_config.etusivu_elastic_client', $elasticClient);

    $request = $this->getMockedRequest('/api/v1/search', parameters: [
      'q' => 'test query',
      'bundle' => 'news',
    ]);
    $response = $this->processRequest($request);
    $this->assertEquals(200, $response->getStatusCode());

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
