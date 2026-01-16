<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Traits;

use Elastic\Elasticsearch\Response\Elasticsearch;
use GuzzleHttp\Psr7\Response;

/**
 * A trait for mocking elastic requests.
 */
trait ElasticTrait {

  /**
   * Mocks elasticsearch response.
   *
   * @param array $response
   *   The response.
   */
  protected function createElasticsearchResponse(array $response): Response {
    return new Response(
      200,
      [
        Elasticsearch::HEADER_CHECK => Elasticsearch::PRODUCT_NAME,
        'Content-Type' => 'application/json',
      ],
      json_encode($response),
    );
  }

}
