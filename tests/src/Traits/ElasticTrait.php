<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Traits;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Elasticsearch\Response\Elasticsearch;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

/**
 * A trait for mocking elastic requests.
 */
trait ElasticTrait {

  /**
   * Mocks elasticsearch response.
   *
   * @param array<mixed> $response
   *   The response.
   */
  protected function createElasticsearchResponse(array $response): Response {
    return new Response(
      200,
      $this->elasticsearchResponseHeaders(),
      (string) json_encode($response),
    );
  }

  /**
   * Builds an Elasticsearch client that replays queued responses.
   *
   * @param array<int, \Psr\Http\Message\ResponseInterface|\Throwable> $responses
   *   Responses or exceptions, returned in order.
   * @param array<int, array<string, mixed>> $history
   *   Outgoing requests are appended here. Omit it to discard them.
   *
   * @return \Elastic\Elasticsearch\Client
   *   The client.
   */
  protected function createMockElasticsearchClient(array $responses, array &$history = []): Client {
    $handlerStack = HandlerStack::create(new MockHandler($responses));
    $recorded = &$history;
    $handlerStack->push(Middleware::history($recorded));

    return ClientBuilder::create()
      ->setHttpClient(new HttpClient(['handler' => $handlerStack]))
      ->build();
  }

  /**
   * Headers the Elasticsearch client requires on a successful response.
   *
   * @return array<string, string>
   *   The headers.
   */
  protected function elasticsearchResponseHeaders(): array {
    return [
      Elasticsearch::HEADER_CHECK => Elasticsearch::PRODUCT_NAME,
      'Content-Type' => 'application/json',
    ];
  }

}
