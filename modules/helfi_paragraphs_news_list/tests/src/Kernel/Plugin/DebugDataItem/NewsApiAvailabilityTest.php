<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_paragraphs_news_list\Kernel\Plugin\DebugData;

use Drupal\helfi_api_base\DebugDataItemPluginManager;
use Drupal\helfi_paragraphs_news_list\Plugin\DebugDataItem\NewsApiAvailability;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\helfi_paragraphs_news_list\Kernel\KernelTestBase;
use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Elastic\Transport\Exception\NoNodeAvailableException;
use GuzzleHttp\Psr7\Response;

/**
 * @coversDefaultClass \Drupal\helfi_paragraphs_news_list\Plugin\DebugDataItem\NewsApiAvailability
 * @group helfi_paragraphs_news_list
 */
class NewsApiAvailabilityTest extends KernelTestBase {

  use ApiTestTrait;

  /**
   * Gets the SUT.
   *
   * @param \GuzzleHttp\Psr7\Response[] $responses
   *   The responses.
   *
   * @return \Drupal\helfi_paragraphs_news_list\Plugin\DebugDataItem\NewsApiAvailability
   *   The SUT.
   */
  public function getSut(array $responses) : NewsApiAvailability {
    $client = $this->createMockHttpClient($responses);
    $elasticClient = ClientBuilder::create()
      ->setHttpClient($client)
      ->build();
    $this->container->set('helfi_platform_config.etusivu_elastic_client', $elasticClient);

    return $this->container->get(DebugDataItemPluginManager::class)
      ->createInstance('news_list');
  }

  /**
   * Make sure check() fails on invalid responses.
   */
  public function testInvalidResponses(): void {
    $types = [
      new NoNodeAvailableException(),
      new ServerResponseException(),
      new ClientResponseException(),
    ];
    foreach ($types as $type) {
      $responses = [];
      for ($i = 0; $i < count(NewsApiAvailability::ENTITY_TYPES); $i++) {
        $responses[] = $type;
      }
      $sut = $this->getSut($responses);
      $this->assertFalse($sut->check());
    }
  }

  /**
   * Tests a successful check().
   */
  public function testCheck(): void {
    $responses = [];
    for ($i = 0; $i < count(NewsApiAvailability::ENTITY_TYPES); $i++) {
      $responses[] = new Response(
        headers: [Elasticsearch::HEADER_CHECK => Elasticsearch::PRODUCT_NAME],
        body: '',
      );
    }
    $sut = $this->getSut($responses);
    $this->assertTrue($sut->check());
  }

}
