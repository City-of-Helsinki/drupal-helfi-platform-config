<?php

declare(strict_types=1);

namespace Drupal\helfi_ai\TextServices;

/**
 * Client for the Helfi Text Services API.
 */
interface TextServicesClientInterface {

  /**
   * Sends a request and returns the complete response.
   *
   * @param \Drupal\helfi_ai\TextServices\TextServicesRequest $request
   *   The request.
   *
   * @return \Drupal\helfi_ai\TextServices\TextServicesResponse
   *   The response.
   *
   * @throws \Drupal\helfi_ai\TextServices\TextServicesException
   */
  public function send(TextServicesRequest $request): TextServicesResponse;

  /**
   * Sends a request and streams the response as events.
   *
   * The API sends a 'start' event, 'delta' events carrying the generated text
   * in parts, and a 'done' event. The request is sent when iteration starts.
   *
   * @param \Drupal\helfi_ai\TextServices\TextServicesRequest $request
   *   The request.
   *
   * @return \Generator<int, array{event: string, data: array<mixed>}, mixed, void>
   *   The events.
   *
   * @throws \Drupal\helfi_ai\TextServices\TextServicesException
   */
  public function stream(TextServicesRequest $request): \Generator;

}
