<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ai\Traits;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use Symfony\Component\HttpFoundation\EventStreamResponse;

/**
 * Mocks the Helfi Text Services API and reads streamed responses.
 */
trait TextServicesTestTrait {

  /**
   * The mocked API responses, returned in order.
   */
  protected MockHandler $textServicesResponses;

  /**
   * Replaces the HTTP client with one that returns mocked responses.
   *
   * Call before any service using the client is created.
   */
  protected function setUpTextServices(): void {
    $this->textServicesResponses = new MockHandler();
    $this->container->set('http_client', new Client([
      'handler' => HandlerStack::create($this->textServicesResponses),
    ]));
  }

  /**
   * Returns the JSON data of the events in a streamed response.
   *
   * @return array<int, array<string, mixed>>
   *   The decoded event data.
   */
  protected function streamedData(EventStreamResponse $response): array {
    ob_start();
    $response->sendContent();
    preg_match_all('/^data: (.*)$/m', (string) ob_get_clean(), $matches);
    return array_map(fn (string $data) => json_decode($data, TRUE), $matches[1]);
  }

}
