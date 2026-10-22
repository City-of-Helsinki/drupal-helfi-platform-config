<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ai\Kernel;

use Drupal\helfi_ai\Controller\TextStreamController;
use Drupal\helfi_ai\Service\TextServicesGenerator;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\Tests\helfi_ai\Traits\TextServicesTestTrait;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\HttpFoundation\EventStreamResponse;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the tone check and summary stream endpoints.
 */
#[Group('helfi_ai')]
#[CoversClass(TextStreamController::class)]
#[RunTestsInSeparateProcesses]
class TextStreamControllerTest extends EntityKernelTestBase {

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
   * The endpoint path, permission and feature flag of each feature.
   */
  private const array FEATURES = [
    'tone' => ['/helfi-ai/tone-check', 'use helfi ai tone check', 'enable_tone_check'],
    'summary' => ['/helfi-ai/summary', 'use helfi ai summary', 'enable_ai_summary'],
  ];

  /**
   * The controller under test.
   */
  private TextStreamController $controller;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['helfi_ai']);
    $this->setUpTextServices();

    $this->config('helfi_ai.settings')
      ->set('enable_tone_check', TRUE)
      ->set('enable_ai_summary', TRUE)
      ->save();

    $this->controller = new TextStreamController(
      $this->container->get(TextServicesGenerator::class),
      $this->container->get('config.factory'),
    );
  }

  /**
   * Builds a POST request to the feature's endpoint.
   *
   * @param string $feature
   *   The feature.
   * @param array<string, mixed> $body
   *   The decoded request body.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   The request.
   */
  private function request(string $feature, array $body): Request {
    return $this->getMockedRequest(self::FEATURES[$feature][0] . '?_format=json', 'POST', document: $body);
  }

  /**
   * A request without the feature's permission is denied.
   */
  #[TestWith(['tone'])]
  #[TestWith(['summary'])]
  public function testWithAccountNoPermission(string $feature): void {
    $this->drupalSetUpCurrentUser();

    $response = $this->processRequest($this->request($feature, ['text' => 'Teksti', 'langcode' => 'fi']));

    $this->assertSame(403, $response->getStatusCode());
    $this->assertSame(sprintf("The '%s' permission is required.", self::FEATURES[$feature][1]), json_decode((string) $response->getContent(), TRUE)['message']);
  }

  /**
   * A request with a session but without a CSRF token is denied.
   */
  #[TestWith(['tone'])]
  #[TestWith(['summary'])]
  public function testNoCsrfToken(string $feature): void {
    $request = $this->request($feature, ['text' => 'Teksti', 'langcode' => 'fi']);
    $options = $this->container->get('session_configuration')->getOptions($request);
    // CsrfRequestHeaderAccessCheck requires a session cookie.
    $request->cookies->set($options['name'], 'arbitrary-session-id-value');
    $this->drupalSetUpCurrentUser(permissions: [self::FEATURES[$feature][1]]);

    $response = $this->processRequest($request);

    $this->assertSame(403, $response->getStatusCode());
    $this->assertStringContainsString('X-CSRF-Token request header is missing', (string) $response->getContent());
  }

  /**
   * Each endpoint checks its own feature flag.
   */
  #[TestWith(['tone'])]
  #[TestWith(['summary'])]
  public function testRouteChecksFeatureFlag(string $feature): void {
    [, $permission, $flag] = self::FEATURES[$feature];
    $this->drupalSetUpCurrentUser(permissions: [$permission]);
    $body = ['text' => ' ', 'langcode' => 'fi'];

    // The flag is enabled: the request reaches the empty text check.
    $this->assertSame(400, $this->processRequest($this->request($feature, $body))->getStatusCode());

    $this->config('helfi_ai.settings')->set($flag, FALSE)->save();
    $response = $this->processRequest($this->request($feature, $body));
    $this->assertSame(403, $response->getStatusCode());
    $this->assertSame('{"error":"The feature is disabled."}', $response->getContent());
  }

  /**
   * The tone check streams the rewrite and then the whole suggestion.
   */
  public function testStreamsTone(): void {
    $this->textServicesResponses->append(new GuzzleResponse(200, ['Content-Type' => 'text/event-stream'], $this->getFixture('helfi_ai', 'tone-stream.txt')));

    $response = $this->controller->stream($this->request('tone', [
      'text' => '<p>Asukaspysäköinti</p>',
      'langcode' => 'fi',
    ]), 'tone');

    $this->assertInstanceOf(EventStreamResponse::class, $response);
    $data = $this->streamedData($response);
    $done = array_pop($data);
    $this->assertSame(['delta' => '<h'], $data[0]);
    $this->assertTrue($done['done']);
    $this->assertStringStartsWith('<h2>Asukaspysäköinti</h2>', $done['result']);
    $this->assertSame('{"text":"<p>Asukaspysäköinti</p>","content_language":"fi","stream":true}', (string) $this->textServicesResponses->getLastRequest()?->getBody());
  }

  /**
   * The summary streams items and the final list.
   */
  public function testStreamsSummary(): void {
    $this->textServicesResponses->append(new GuzzleResponse(200, ['Content-Type' => 'text/event-stream'], $this->getFixture('helfi_ai', 'summarize-stream.txt')));

    $response = $this->controller->stream($this->request('summary', [
      'text' => 'Asukaspysäköinti',
      'langcode' => 'fi',
    ]), 'summary');

    $this->assertInstanceOf(EventStreamResponse::class, $response);
    $this->assertSame('no', $response->headers->get('X-Accel-Buffering'));
    $data = $this->streamedData($response);
    $done = array_pop($data);
    $this->assertCount(5, $data[count($data) - 1]['items']);
    $this->assertTrue($done['done']);
    $this->assertSame(5, substr_count($done['result'], '<li>'));
    $this->assertStringEndsWith('/v1/summarization', (string) $this->textServicesResponses->getLastRequest()?->getUri());
  }

  /**
   * A missing or non-string parameter is a bad request.
   *
   * @param 'tone'|'summary' $feature
   *   The feature.
   * @param array<string, mixed> $body
   *   The request body.
   */
  #[TestWith(['tone', ['text' => '<p>Hi</p>']])]
  #[TestWith(['tone', ['content' => '<p>Hi</p>', 'langcode' => 'fi']])]
  #[TestWith(['summary', ['text' => [], 'langcode' => 'fi']])]
  #[TestWith(['summary', ['text' => ['nested' => 'object'], 'langcode' => 'fi']])]
  #[TestWith(['summary', ['text' => 'Teksti', 'langcode' => ['fi']]])]
  public function testRejectsInvalidParameters(string $feature, array $body): void {
    $this->expectException(BadRequestException::class);
    $this->controller->stream($this->request($feature, $body), $feature);
  }

  /**
   * Empty or too long text is rejected without calling the API.
   *
   * @param 'tone'|'summary' $feature
   *   The feature.
   */
  #[TestWith(['tone'])]
  #[TestWith(['summary'])]
  public function testRejectsInvalidText(string $feature): void {
    $empty = $this->controller->stream($this->request($feature, ['text' => '   ', 'langcode' => 'fi']), $feature);
    $this->assertSame(400, $empty->getStatusCode());

    $tooLong = str_repeat('ä', TextServicesGenerator::MAX_CONTENT_LENGTH + 1);
    $response = $this->controller->stream($this->request($feature, ['text' => $tooLong, 'langcode' => 'fi']), $feature);
    $this->assertSame(413, $response->getStatusCode());

    $this->assertNull($this->textServicesResponses->getLastRequest());
  }

  /**
   * A failed API request streams an error event.
   *
   * @param 'tone'|'summary' $feature
   *   The feature.
   */
  #[TestWith(['tone'])]
  #[TestWith(['summary'])]
  public function testStreamsErrorWhenRequestFails(string $feature): void {
    $this->textServicesResponses->append(new GuzzleResponse(502, [], '{"error": "Model request failed"}'));

    $response = $this->controller->stream($this->request($feature, ['text' => 'Teksti', 'langcode' => 'fi']), $feature);

    $this->assertInstanceOf(EventStreamResponse::class, $response);
    $this->assertSame([['error' => TRUE]], $this->streamedData($response));
  }

}
