<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_recommendations\Unit\Plugin\DebugData;

use Drupal\helfi_recommendations\Plugin\DebugDataItem\ApiAvailability;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\helfi_api_base\Traits\EnvironmentResolverTrait;
use Drupal\Tests\UnitTestCase;
use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Elastic\Transport\Exception\NoNodeAvailableException;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Utils;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * @coversDefaultClass \Drupal\helfi_recommendations\Plugin\DebugDataItem\ApiAvailability
 * @group helfi_recommendations
 */
class ApiAvailabilityTest extends UnitTestCase {

  use ProphecyTrait;
  use ApiTestTrait;
  use EnvironmentResolverTrait;

  /**
   * Gets the SUT.
   *
   * @param \GuzzleHttp\Psr7\Response[] $responses
   *   The responses.
   *
   * @return \Drupal\helfi_recommendations\Plugin\DebugDataItem\ApiAvailability
   *   The SUT.
   */
  public function getSut(array $responses) : ApiAvailability {
    $client = $this->createMockHttpClient($responses);
    $elasticClient = ClientBuilder::create()
      ->setHttpClient($client)
      ->build();
    return new ApiAvailability([], '', '', $elasticClient);
  }

  /**
   * Make sure check() fails on invalid responses.
   */
  public function testInvalidResponses(): void {
    $responses = [
      // Invalid json should result in GuzzleException.
      new Response(headers: [Elasticsearch::HEADER_CHECK => Elasticsearch::PRODUCT_NAME], body: NULL),
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
    $sut = $this->getSut([
      new Response(
        headers: [Elasticsearch::HEADER_CHECK => Elasticsearch::PRODUCT_NAME],
        body: Utils::jsonEncode([
          'version' => 123,
        ])
      ),
    ]);
    $this->assertTrue($sut->check());
  }

}
