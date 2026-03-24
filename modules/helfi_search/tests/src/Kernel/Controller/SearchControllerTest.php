<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_search\Kernel\Controller;

use Drupal\helfi_search\Controller\SearchController;
use Drupal\helfi_search\EmbeddingsModelInterface;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\helfi_platform_config\Kernel\KernelTestBase;
use Drupal\Tests\helfi_platform_config\Traits\ElasticTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Elastic\Elasticsearch\ClientBuilder;
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

    // Configure at least one model.
    $this->config('helfi_search.settings')
      ->set('openai_models', ['text-embedding-3-small'])
      ->save();
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
    $embeddingsModel->getEmbedding(Argument::type('string'), Argument::type('string'))
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

    $this->container->set('helfi_search.etusivu_elastic_client', $client);

    // Test empty results.
    $request = $this->getMockedRequest('/api/v1/search', parameters: ['q' => 'no results query']);
    $response = $this->processRequest($request);

    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($response->getContent(), TRUE);
    $this->assertEmpty($data['results']);
    $this->assertEmpty($data['promoted']);

    // Test with promoted and KNN results.
    $request = $this->getMockedRequest('/api/v1/search', parameters: ['q' => 'test query']);
    $response = $this->processRequest($request);

    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($response->getContent(), TRUE);
    $this->assertCount(1, $data['promoted']);
    $this->assertEquals('Promoted Result', $data['promoted'][0]['title']);
    $this->assertEquals('A promoted description', $data['promoted'][0]['description']);
    $this->assertEquals('/fi/promoted', $data['promoted'][0]['url']);
    $this->assertEquals('fi', $data['promoted'][0]['language']);
    $this->assertCount(1, $data['results']);
    $this->assertEquals(0.95, $data['results'][0]['score']);
    $this->assertEquals('node', $data['results'][0]['entity_type']);
    $this->assertEquals('/fi/test-page', $data['results'][0]['url']);
    $this->assertEquals('Test Page', $data['results'][0]['title']);
    $this->assertEquals('fi', $data['results'][0]['language']);

    // Test promotion error is handled gracefully.
    $request = $this->getMockedRequest('/api/v1/search', parameters: ['q' => 'test query']);
    $response = $this->processRequest($request);

    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($response->getContent(), TRUE);
    $this->assertEmpty($data['promoted']);
    $this->assertCount(1, $data['results']);
    $this->assertEquals('/fi/fallback', $data['results'][0]['url']);

    // Test total ES failure.
    $request = $this->getMockedRequest('/api/v1/search', parameters: ['q' => 'test query']);
    $response = $this->processRequest($request);

    $this->assertEquals(503, $response->getStatusCode());
  }

}
