<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ai\Unit\TextServices;

use Drupal\helfi_ai\TextServices\Service;
use Drupal\helfi_ai\TextServices\TextServicesClient;
use Drupal\helfi_ai\TextServices\TextServicesException;
use Drupal\helfi_ai\TextServices\TextServicesRequest;
use Drupal\helfi_ai\TextServices\TextServicesResponse;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestWith;

/**
 * Tests the Helfi Text Services client.
 */
#[Group('helfi_ai')]
#[CoversClass(TextServicesClient::class)]
#[CoversClass(TextServicesResponse::class)]
class TextServicesClientTest extends UnitTestCase {

  use ApiTestTrait;

  /**
   * Request history.
   *
   * @var array<int, array{request: \Psr\Http\Message\RequestInterface, options: array<string, mixed>}>
   */
  private array $history = [];

  /**
   * Creates a client that returns the given responses.
   *
   * @param array<\Psr\Http\Message\ResponseInterface|\GuzzleHttp\Exception\GuzzleException> $responses
   *   The responses.
   *
   * @return \Drupal\helfi_ai\TextServices\TextServicesClient
   *   The client.
   */
  private function createClient(array $responses): TextServicesClient {
    return new TextServicesClient(
      $this->createMockHistoryMiddlewareHttpClient($this->history, $responses),
      $this->getConfigFactoryStub([
        'helfi_ai.settings' => ['text_services' => ['api_key' => 'test-key']],
      ]),
    );
  }

  /**
   * Reads a fixture file.
   */
  private function fixture(string $name): string {
    return (string) file_get_contents(__DIR__ . '/../../../fixtures/' . $name);
  }

  /**
   * A request is posted to the service with the key and unescaped text.
   */
  public function testSendRequest(): void {
    $client = $this->createClient([new Response(200, [], $this->fixture('tone.json'))]);

    $response = $client->send(new TextServicesRequest(Service::Tone, '<p>Hyvää päivää</p>', 'fi'));

    $this->assertStringStartsWith('<h2>Asukaspysäköinti</h2>', $response->text);
    $this->assertSame('gpt-5.1', $response->model);

    $request = $this->history[0]['request'];
    $this->assertSame('POST', $request->getMethod());
    $this->assertSame(TextServicesClient::BASE_URL . '/v1/tone', (string) $request->getUri());
    $this->assertSame('test-key', $request->getHeaderLine('X-API-Key'));
    $this->assertSame('application/json', $request->getHeaderLine('Content-Type'));
    // The language is not sent, and the text is not unicode-escaped.
    $this->assertSame('{"text":"<p>Hyvää päivää</p>"}', (string) $request->getBody());
  }

  /**
   * A JSON schema is sent as the response format.
   */
  public function testSendJsonSchema(): void {
    $client = $this->createClient([new Response(200, [], $this->fixture('summarize.json'))]);
    $schema = ['type' => 'object'];

    $client->send(new TextServicesRequest(Service::Summarize, 'Teksti', 'fi', $schema));

    $request = $this->history[0]['request'];
    $this->assertSame(TextServicesClient::BASE_URL . '/dev/summarize', (string) $request->getUri());
    $this->assertSame([
      'text' => 'Teksti',
      'response_format' => 'json_schema',
      'json_schema' => $schema,
    ], json_decode((string) $request->getBody(), TRUE));
  }

  /**
   * An error response throws with the HTTP status and the error message.
   */
  #[TestWith([400, '{"error": "Something failed"}', 'Something failed'])]
  #[TestWith([401, '{"error": "Something failed"}', 'Something failed'])]
  #[TestWith([502, '<html>Bad Gateway</html>', 'Bad Gateway'])]
  public function testErrorResponse(int $status, string $body, string $error): void {
    $client = $this->createClient([new Response($status, [], $body)]);

    $this->expectException(TextServicesException::class);
    $this->expectExceptionCode($status);
    $this->expectExceptionMessage("HTTP $status: $error");
    $client->send(new TextServicesRequest(Service::Tone, 'Teksti', 'fi'));
  }

  /**
   * A connection error throws with code 0.
   */
  public function testConnectionError(): void {
    $client = $this->createClient([
      new ConnectException('Connection timed out', new Request('POST', TextServicesClient::BASE_URL)),
    ]);

    $this->expectException(TextServicesException::class);
    $this->expectExceptionCode(0);
    $this->expectExceptionMessage('Connection timed out');
    $client->send(new TextServicesRequest(Service::Tone, 'Teksti', 'fi'));
  }

  /**
   * A response that is not a valid API response throws.
   */
  #[TestWith(['not json'])]
  #[TestWith(['{"model": "gpt-5.1"}'])]
  public function testInvalidResponse(string $body): void {
    $client = $this->createClient([new Response(200, [], $body)]);

    $this->expectException(TextServicesException::class);
    $this->expectExceptionMessage('Invalid response');
    $client->send(new TextServicesRequest(Service::Tone, 'Teksti', 'fi'));
  }

  /**
   * A streamed response yields the decoded events.
   */
  public function testStream(): void {
    $client = $this->createClient([
      new Response(200, ['Content-Type' => 'text/event-stream'], $this->fixture('tone-stream.txt')),
    ]);

    $events = iterator_to_array($client->stream(new TextServicesRequest(Service::Tone, 'Teksti', 'fi')), FALSE);

    $this->assertSame(['event' => 'start', 'data' => ['model' => 'gpt-5.1']], $events[0]);
    $this->assertSame('done', end($events)['event']);
    $this->assertSame(['event' => 'delta', 'data' => ['text' => '<h']], $events[1]);

    $this->assertSame(['text' => 'Teksti', 'stream' => TRUE], json_decode((string) $this->history[0]['request']->getBody(), TRUE));
    $this->assertTrue($this->history[0]['options']['stream']);
  }

  /**
   * An error event in the stream throws.
   */
  public function testStreamErrorEvent(): void {
    $body = "event: start\ndata: {\"model\": \"gpt-5.1\"}\n\nevent: error\ndata: {\"error\": \"Model request failed\"}\n\n";
    $client = $this->createClient([new Response(200, [], $body)]);

    $this->expectException(TextServicesException::class);
    $this->expectExceptionMessage('Model request failed');
    iterator_to_array($client->stream(new TextServicesRequest(Service::Tone, 'Teksti', 'fi')));
  }

  /**
   * A stream that ends before the done event throws.
   */
  public function testStreamEndsBeforeDone(): void {
    $body = "event: start\ndata: {\"model\": \"gpt-5.1\"}\n\nevent: delta\ndata: {\"text\": \"Hei\"}\n\n";
    $client = $this->createClient([new Response(200, [], $body)]);

    $this->expectException(TextServicesException::class);
    $this->expectExceptionMessage('ended before the done event');
    iterator_to_array($client->stream(new TextServicesRequest(Service::Tone, 'Teksti', 'fi')));
  }

}
