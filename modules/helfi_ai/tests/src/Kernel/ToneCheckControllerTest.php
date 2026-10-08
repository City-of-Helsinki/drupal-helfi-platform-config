<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ai\Kernel;

use Drupal\helfi_ai\Controller\ToneCheckController;
use Drupal\helfi_ai\Service\TextServicesGenerator;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\Tests\helfi_ai\Traits\TextServicesTestTrait;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the tone-check controller endpoint.
 */
#[Group('helfi_ai')]
#[CoversClass(ToneCheckController::class)]
#[RunTestsInSeparateProcesses]
class ToneCheckControllerTest extends EntityKernelTestBase {

  use ApiTestTrait;
  use TextServicesTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_platform_config',
    'diff',
    'helfi_api_base',
    'config_rewrite',
    'node',
    'language',
    'key',
    'ai',
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

    $this->installConfig(['helfi_ai']);
    $this->setUpTextServices();

    // Enable the AI tone check functionality.
    $this->config('helfi_ai.settings')
      ->set('enable_tone_check', TRUE)
      ->save();

    $this->controller = new ToneCheckController(
      $this->container->get(TextServicesGenerator::class),
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
   * Valid content returns the rewrite suggestion as JSON.
   */
  public function testReturnsSuggestionForValidContent(): void {
    $this->textServicesResponses->append(new Response(200, [], $this->getFixture('helfi_ai', 'tone.json')));

    $response = $this->controller->check($this->request([
      'content' => '<p>Asukaspysäköinti</p>',
      'langcode' => 'fi',
    ]));

    $this->assertSame(200, $response->getStatusCode());
    $this->assertStringStartsWith('<h2>Asukaspysäköinti</h2>', $this->decode($response)['suggestion']);
    $this->assertSame('{"text":"<p>Asukaspysäköinti</p>"}', (string) $this->textServicesResponses->getLastRequest()?->getBody());
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
   * Empty content is rejected with 400 without calling the API.
   */
  public function testRejectsEmptyContent(): void {
    $response = $this->controller->check($this->request([
      'content' => '   ',
      'langcode' => 'en',
    ]));

    $this->assertSame(400, $response->getStatusCode());
    $this->assertArrayHasKey('error', $this->decode($response));
    $this->assertNull($this->textServicesResponses->getLastRequest());
  }

  /**
   * Content longer than the API accepts is rejected with 413.
   */
  public function testRejectsTooLargeContent(): void {
    $response = $this->controller->check($this->request([
      'content' => str_repeat('ä', TextServicesGenerator::MAX_CONTENT_LENGTH + 1),
      'langcode' => 'fi',
    ]));

    $this->assertSame(413, $response->getStatusCode());
    $this->assertNull($this->textServicesResponses->getLastRequest());
  }

  /**
   * A failed API request yields a 400.
   */
  public function testReturns400WhenRequestFails(): void {
    $this->textServicesResponses->append(new Response(502, [], '{"error": "Model request failed"}'));

    $response = $this->controller->check($this->request([
      'content' => '<p>Hi</p>',
      'langcode' => 'en',
    ]));

    $this->assertSame(400, $response->getStatusCode());
    $this->assertArrayHasKey('error', $this->decode($response));
  }

}
