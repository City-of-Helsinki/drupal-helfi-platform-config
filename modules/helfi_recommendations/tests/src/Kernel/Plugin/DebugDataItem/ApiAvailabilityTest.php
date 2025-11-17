<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_paragraphs_news_list\Kernel\Plugin\DebugData;

use Drupal\helfi_api_base\DebugDataItemPluginManager;
use Drupal\helfi_recommendations\Plugin\DebugDataItem\ApiAvailability;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\helfi_recommendations\Kernel\AnnifKernelTestBase;
use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Elastic\Transport\Exception\NoNodeAvailableException;
use GuzzleHttp\Psr7\Response;

/**
 * @coversDefaultClass \Drupal\helfi_recommendations\Plugin\DebugDataItem\ApiAvailability
 * @group helfi_recommendations
 */
class ApiAvailabilityTest extends AnnifKernelTestBase {

  use ApiTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
  ];

  /**
   * Gets the SUT.
   *
   * @param \GuzzleHttp\Psr7\Response[] $responses
   *   The responses.
   */
  public function getSut(array $responses) : ApiAvailability {
    $client = $this->createMockHttpClient($responses);
    $elasticClient = ClientBuilder::create()
      ->setHttpClient($client)
      ->build();
    $this->container->set('helfi_platform_config.etusivu_elastic_client', $elasticClient);

    return $this->container->get(DebugDataItemPluginManager::class)
      ->createInstance('recommendations');
  }

  /**
   * Make sure check() fails on invalid responses.
   */
  public function testInvalidResponses(): void {
    $responses = [
      new NoNodeAvailableException(),
      new ServerResponseException(),
      new ClientResponseException(),
    ];
    $sut = $this->getSut($responses);

    for ($i = 0; $i < count($responses); $i++) {
      $this->assertFalse($sut->check());
    }
  }

  /**
   * Tests a successful check().
   */
  public function testCheck(): void {
    $response = new Response(
      headers: [Elasticsearch::HEADER_CHECK => Elasticsearch::PRODUCT_NAME],
      body: '',
    );
    $sut = $this->getSut([$response]);
    $this->assertTrue($sut->check());
  }

}
