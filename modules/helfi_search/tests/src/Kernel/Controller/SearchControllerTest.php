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
    $embeddingsModel->getEmbedding(Argument::type('string'))
      ->willReturn(array_fill(0, 3, 0.1));

    $this->container->set(EmbeddingsModelInterface::class, $embeddingsModel->reveal());

    $client = ClientBuilder::create()
      ->setHttpClient($this->createMockHttpClient([
        // Empty response.
        $this->createElasticsearchResponse([
          'hits' => ['hits' => []],
        ]),
        // One results.
        $this->createElasticsearchResponse([
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
        ]),
        // Error response.
        new Response(500, [], 'Internal Server Error'),
      ]))
      ->build();

    $this->container->set('helfi_platform_config.etusivu_elastic_client', $client);

    $request = $this->getMockedRequest('/api/v1/search', parameters: ['q' => 'no results query']);
    $response = $this->processRequest($request);

    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($response->getContent(), TRUE);
    $this->assertEmpty($data['results']);

    $request = $this->getMockedRequest('/api/v1/search', parameters: ['q' => 'test query']);
    $response = $this->processRequest($request);

    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($response->getContent(), TRUE);
    $this->assertCount(1, $data['results']);
    $this->assertEquals(0.95, $data['results'][0]['score']);
    $this->assertEquals('node', $data['results'][0]['entity_type']);
    $this->assertEquals('/fi/test-page', $data['results'][0]['url']);
    $this->assertEquals('Test Page', $data['results'][0]['title']);
    $this->assertEquals('fi', $data['results'][0]['language']);

    $request = $this->getMockedRequest('/api/v1/search', parameters: ['q' => 'test query']);
    $response = $this->processRequest($request);

    $this->assertEquals(503, $response->getStatusCode());
  }

}
