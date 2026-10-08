<?php

declare(strict_types=1);

namespace Drupal\helfi_ai\TextServices;

use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

/**
 * Client for the Helfi Text Services API.
 */
class TextServicesClient implements TextServicesClientInterface {

  /**
   * The API base URL.
   */
  public const string BASE_URL = 'https://helfi-text-services.azurewebsites.net/api';

  /**
   * Request timeout in seconds. Generation can take up to 90 seconds.
   */
  private const int TIMEOUT = 120;

  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly ConfigFactoryInterface $configFactory,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function send(TextServicesRequest $request): TextServicesResponse {
    $response = $this->request($request, FALSE);

    try {
      $data = json_decode((string) $response->getBody(), TRUE, flags: JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $e) {
      throw new TextServicesException('Invalid response from Helfi Text Services', previous: $e);
    }
    return TextServicesResponse::fromArray(is_array($data) ? $data : []);
  }

  /**
   * {@inheritdoc}
   */
  public function stream(TextServicesRequest $request): \Generator {
    $body = $this->request($request, TRUE)->getBody();
    $parser = new SseParser();
    $done = FALSE;

    while (!$body->eof()) {
      foreach ($parser->feed($body->read(8192)) as $event) {
        $data = json_decode($event['data'], TRUE);

        if (!is_array($data)) {
          throw new TextServicesException('Invalid stream event from Helfi Text Services');
        }
        if ($event['event'] === 'error') {
          throw new TextServicesException(sprintf('Helfi Text Services stream failed: %s', $data['error'] ?? 'unknown error'));
        }
        $done = $event['event'] === 'done';
        yield ['event' => $event['event'], 'data' => $data];
      }
    }

    if (!$done) {
      throw new TextServicesException('Helfi Text Services stream ended before the done event');
    }
  }

  /**
   * Sends the request to the API.
   *
   * @param \Drupal\helfi_ai\TextServices\TextServicesRequest $request
   *   The request.
   * @param bool $stream
   *   Whether to request a streamed response.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The successful response.
   *
   * @throws \Drupal\helfi_ai\TextServices\TextServicesException
   */
  private function request(TextServicesRequest $request, bool $stream): ResponseInterface {
    $payload = ['text' => $request->text];
    // The API has no language field yet, and it rejects unknown fields with
    // 400, so $request->langcode is not sent.
    if ($stream) {
      $payload['stream'] = TRUE;
    }
    if ($request->jsonSchema !== NULL) {
      $payload['response_format'] = 'json_schema';
      $payload['json_schema'] = $request->jsonSchema;
    }

    // Guzzle's 'json' option escapes non-ASCII characters and slashes,
    // which would waste the API's request size limit.
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

    try {
      return $this->httpClient->request('POST', self::BASE_URL . '/' . $request->service->value, [
        'headers' => [
          'X-API-Key' => (string) $this->configFactory->get('helfi_ai.settings')->get('text_services.api_key'),
          'Content-Type' => 'application/json',
        ],
        'body' => $body,
        'timeout' => self::TIMEOUT,
        'stream' => $stream,
      ]);
    }
    catch (GuzzleException $e) {
      throw $this->createException($e);
    }
  }

  /**
   * Creates an exception for a failed request.
   *
   * Uses the error message and HTTP status from the API response if there
   * is one.
   *
   * @param \GuzzleHttp\Exception\GuzzleException $e
   *   The request error.
   *
   * @return \Drupal\helfi_ai\TextServices\TextServicesException
   *   The exception.
   */
  private function createException(GuzzleException $e): TextServicesException {
    $response = $e instanceof RequestException ? $e->getResponse() : NULL;

    if (!$response) {
      return new TextServicesException(sprintf('Helfi Text Services request failed: %s', $e->getMessage()), previous: $e);
    }
    $data = json_decode((string) $response->getBody(), TRUE);
    $error = is_array($data) && is_string($data['error'] ?? NULL) ? $data['error'] : $response->getReasonPhrase();

    return new TextServicesException(
      sprintf('Helfi Text Services request failed: HTTP %d: %s', $response->getStatusCode(), $error),
      $response->getStatusCode(),
      $e,
    );
  }

}
