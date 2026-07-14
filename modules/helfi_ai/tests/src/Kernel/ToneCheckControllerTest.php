<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ai\Kernel;

use Drupal\helfi_ai\Controller\ToneCheckController;
use Drupal\helfi_ai\Service\AiGenerator;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
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

  use ApiTestTrait;

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
    'system',
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

    // Enable the AI tone check functionality.
    $this->config('helfi_ai.settings')
      ->set('enable_tone_check', TRUE)
      ->save();

    $this->controller = new ToneCheckController(
      $this->container->get(AiGenerator::class),
      $this->container->get('config.factory'),
    );
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
    return $this->getMockedRequest('/helfi-ai/tone-check?_format=json', 'POST', document: $body);
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
   * A request without permissions CSRF token should fail with 403.
   */
  public function testNoPermissionNoCsrfToken(): void {
    $response = $this->processRequest($this->request([
      'content' => '123',
      'langcode' => 'en',
    ]));
    $this->assertSame(403, $response->getStatusCode());
  }

  /**
   * A request with a logged-in user without permissions should fail with 403.
   */
  public function testWithAccountNoPermission(): void {
    $user = $this->drupalSetUpCurrentUser();
    $this->assertFalse($user->hasPermission('use helfi ai tone check'));

    $response = $this->processRequest($this->request([
      'content' => '123',
      'langcode' => 'en',
    ]));
    $this->assertInstanceOf(JsonResponse::class, $response);

    $content = $this->decode($response);
    $this->assertSame("The 'use helfi ai tone check' permission is required.", $content['message']);
    $this->assertSame(403, $response->getStatusCode());
  }

  /**
   * A request with a logged-in user without csrf-token should fail with 403.
   */
  public function testNoCsrfToken(): void {
    $request = $this->request([
      'content' => '123',
      'langcode' => 'en',
    ]);
    /** @var \Drupal\Core\Session\SessionConfigurationInterface $sessionConfiguration */
    $sessionConfiguration = $this->container->get('session_configuration');
    $options = $sessionConfiguration->getOptions($request);
    // CsrfRequestHeaderAccessCheck requires session cookie.
    $request->cookies->set($options['name'], 'arbitrary-session-id-value');

    $this->drupalSetUpCurrentUser(permissions: ['use helfi ai tone check']);

    $response = $this->processRequest($request);
    $this->assertInstanceOf(JsonResponse::class, $response);

    $content = $this->decode($response);
    $this->assertSame('X-CSRF-Token request header is missing', $content['message']);
    $this->assertSame(403, $response->getStatusCode());
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
   * Test that a disabled feature rejects the request with 403.
   */
  public function testRejectsWhenFeatureDisabled(): void {
    $this->config('helfi_ai.settings')->set('enable_tone_check', FALSE)->save();

    $response = $this->controller->check($this->request([
      'content' => '<p>Test</p>',
      'langcode' => 'en',
    ]));

    $this->assertSame(403, $response->getStatusCode());
    $this->assertArrayHasKey('error', $this->decode($response));
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
