<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ai\Kernel;

use Drupal\helfi_ai\Controller\ToneCheckController;
use Drupal\helfi_ai\Service\AiGenerator;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the tone-check controller endpoint through the echoai test provider.
 */
#[Group('helfi_ai')]
#[CoversClass(ToneCheckController::class)]
#[RunTestsInSeparateProcesses]
class ToneCheckControllerTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_platform_config',
    'helfi_api_base',
    'config_rewrite',
    'node',
    'language',
    'key',
    'ai',
    'ai_test',
    'helfi_ai',
  ];

  /**
   * The controller under test.
   */
  private ToneCheckController $controller;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['ai', 'ai_test', 'helfi_ai']);
    $this->installEntitySchema('ai_mock_provider_result');

    // Resolve chat operations to the echoai test provider.
    $this->config('ai.settings')
      ->set('default_providers', [
        'chat' => ['provider_id' => 'echoai', 'model_id' => 'test'],
      ])
      ->save();

    $this->controller = new ToneCheckController($this->container->get(AiGenerator::class));
  }

  /**
   * Builds a POST request carrying the given JSON body.
   *
   * @param array<string, mixed> $body
   *   The decoded request body.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   The request.
   */
  private function request(array $body): Request {
    return new Request([], [], [], [], [], [], (string) json_encode($body));
  }

  /**
   * Decodes a JSON response body to an array.
   *
   * @param \Symfony\Component\HttpFoundation\JsonResponse $response
   *   The response.
   *
   * @return array<string, mixed>
   *   The decoded payload.
   */
  private function decode(JsonResponse $response): array {
    return json_decode((string) $response->getContent(), TRUE);
  }

  /**
   * Valid content returns a rewrite suggestion as JSON.
   */
  public function testReturnsSuggestionForValidContent(): void {
    $content = '<p>Tone controller content ' . $this->randomMachineName() . '</p>';

    $response = $this->controller->check($this->request([
      'content' => $content,
      'langcode' => 'en',
    ]));

    $this->assertSame(200, $response->getStatusCode());
    $payload = $this->decode($response);
    $this->assertArrayHasKey('suggestion', $payload);
    // The echoed prompt proves the content reached the provider.
    $this->assertStringContainsString($content, $payload['suggestion']);
  }

  /**
   * A missing content or langcode parameter is a bad request.
   */
  public function testRejectsMissingParameters(): void {
    $this->expectException(BadRequestException::class);
    $this->controller->check($this->request(['content' => '<p>Hi</p>']));
  }

  /**
   * Empty content is rejected with 400 without calling the provider.
   */
  public function testRejectsEmptyContent(): void {
    $response = $this->controller->check($this->request([
      'content' => '   ',
      'langcode' => 'en',
    ]));

    $this->assertSame(400, $response->getStatusCode());
    $this->assertArrayHasKey('error', $this->decode($response));
  }

  /**
   * Content larger than the byte cap is rejected with 413.
   */
  public function testRejectsTooLargeContent(): void {
    $response = $this->controller->check($this->request([
      'content' => str_repeat('a', AiGenerator::MAX_CONTENT_BYTES + 1),
      'langcode' => 'en',
    ]));

    $this->assertSame(413, $response->getStatusCode());
  }

  /**
   * An unresolvable provider yields a 502.
   */
  public function testReturns502WhenProviderUnavailable(): void {
    $this->config('ai.settings')
      ->set('default_providers', [
        'chat' => ['provider_id' => 'no_such_provider', 'model_id' => 'test'],
      ])
      ->save();

    $response = $this->controller->check($this->request([
      'content' => '<p>Hi</p>',
      'langcode' => 'en',
    ]));

    $this->assertSame(502, $response->getStatusCode());
    $this->assertArrayHasKey('error', $this->decode($response));
  }

}
