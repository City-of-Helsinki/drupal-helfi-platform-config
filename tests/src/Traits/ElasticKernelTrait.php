<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Traits;

use Elastic\Elasticsearch\Client;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;

/**
 * Installs mocked Elasticsearch clients on the kernel test container.
 *
 * @phpstan-require-extends \Drupal\KernelTests\KernelTestBase
 */
trait ElasticKernelTrait {

  use ElasticTrait;

  /**
   * Installs a mocked etusivu Elasticsearch client.
   *
   * Replaces helfi_platform_config.etusivu_elastic_client.
   *
   * @param array<int, \Psr\Http\Message\ResponseInterface|\Throwable> $responses
   *   Responses or exceptions, returned in order.
   * @param array<int, array<string, mixed>> $history
   *   Outgoing requests are appended here. Omit it to discard them.
   *
   * @return \Elastic\Elasticsearch\Client
   *   The installed client.
   */
  protected function mockEtusivuElasticClient(array $responses, array &$history = []): Client {
    $client = $this->createMockElasticsearchClient($responses, $history);
    $this->container->set('helfi_platform_config.etusivu_elastic_client', $client);
    return $client;
  }

  /**
   * Stubs Drupal's http_client for the Elasticsearch connector.
   *
   * The search API connector reads http_client when its backend is built,
   * then may call Elasticsearch again while indexing. Every call gets the
   * same response, so those tests stay off the network.
   *
   * @param array<mixed> $response
   *   The JSON object returned for every request. An empty array is encoded
   *   as an empty object, which is what the client accepts for a no-op call.
   */
  protected function mockElasticsearchConnectorHttpClient(array $response = []): void {
    $this->container->set('http_client', new HttpClient([
      'handler' => function () use ($response) {
        // A fresh response each time: the client consumes the body stream.
        $body = $response === [] ? '{}' : (string) json_encode($response);
        return Create::promiseFor(new Response(
          200,
          $this->elasticsearchResponseHeaders(),
          $body,
        ));
      },
    ]));
  }

}
