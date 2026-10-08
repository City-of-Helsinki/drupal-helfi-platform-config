<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ai\Traits;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;

/**
 * Replaces the Helfi Text Services API with mocked responses.
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

}
