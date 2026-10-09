<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ai\Kernel;

use Drupal\helfi_ai\Controller\SummaryController;
use Drupal\helfi_ai\Service\TextServicesGenerator;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\Tests\helfi_ai\Traits\TextServicesTestTrait;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\EventStreamResponse;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the summary stream endpoint.
 */
#[Group('helfi_ai')]
#[CoversClass(SummaryController::class)]
#[RunTestsInSeparateProcesses]
class SummaryControllerTest extends EntityKernelTestBase {

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
  private SummaryController $controller;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['helfi_ai']);
    $this->setUpTextServices();

    $this->config('helfi_ai.settings')
      ->set('enable_ai_summary', TRUE)
      ->save();

    $this->controller = new SummaryController(
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
    return $this->getMockedRequest('/helfi-ai/summary?_format=json', 'POST', document: $body);
  }

  /**
   * A request without the permission is denied.
   */
  public function testWithAccountNoPermission(): void {
    $this->drupalSetUpCurrentUser();

    $response = $this->processRequest($this->request(['text' => 'Page content', 'langcode' => 'fi']));

    $this->assertSame(403, $response->getStatusCode());
  }

  /**
   * A request with a session but without a CSRF token is denied.
   */
  public function testNoCsrfToken(): void {
    $request = $this->request(['text' => 'Page content', 'langcode' => 'fi']);
    $options = $this->container->get('session_configuration')->getOptions($request);
    // CsrfRequestHeaderAccessCheck requires a session cookie.
    $request->cookies->set($options['name'], 'arbitrary-session-id-value');
    $this->drupalSetUpCurrentUser(permissions: ['use helfi ai summary']);

    $response = $this->processRequest($request);

    $this->assertSame(403, $response->getStatusCode());
    $this->assertStringContainsString('X-CSRF-Token request header is missing', (string) $response->getContent());
  }

  /**
   * The summary streams items and the final list.
   */
  public function testStreamsSummary(): void {
    $this->textServicesResponses->append(new Response(200, ['Content-Type' => 'text/event-stream'], $this->getFixture('helfi_ai', 'summarize-stream.txt')));

    $response = $this->controller->summary($this->request(['text' => 'Asukaspysäköinti', 'langcode' => 'fi']));

    $this->assertInstanceOf(EventStreamResponse::class, $response);
    $this->assertSame('no', $response->headers->get('X-Accel-Buffering'));
    $data = $this->streamedData($response);
    $done = array_pop($data);
    $this->assertCount(5, $data[count($data) - 1]['items']);
    $this->assertTrue($done['done']);
    $this->assertStringContainsString('<li>Haet asukaspysäköintitunnuksen', $done['result']);

    $sent = json_decode((string) $this->textServicesResponses->getLastRequest()?->getBody(), TRUE);
    $this->assertSame('Asukaspysäköinti', $sent['text']);
    $this->assertTrue($sent['stream']);
  }

  /**
   * A failed API request streams an error event.
   */
  public function testStreamsErrorWhenRequestFails(): void {
    $this->textServicesResponses->append(new Response(502, [], '{"error": "Model request failed"}'));

    $response = $this->controller->summary($this->request(['text' => 'Page content', 'langcode' => 'fi']));

    $this->assertInstanceOf(EventStreamResponse::class, $response);
    $this->assertSame([['error' => TRUE]], $this->streamedData($response));
  }

  /**
   * A disabled feature rejects the request with 403.
   */
  public function testRejectsWhenFeatureDisabled(): void {
    $this->config('helfi_ai.settings')->set('enable_ai_summary', FALSE)->save();

    $response = $this->controller->summary($this->request(['text' => 'Page content', 'langcode' => 'fi']));

    $this->assertSame(403, $response->getStatusCode());
  }

  /**
   * A missing text or langcode parameter is a bad request.
   */
  public function testRejectsMissingParameters(): void {
    $this->expectException(BadRequestException::class);
    $this->controller->summary($this->request(['text' => 'Page content']));
  }

  /**
   * Empty or too long text is rejected without calling the API.
   */
  public function testRejectsInvalidText(): void {
    $this->assertSame(400, $this->controller->summary($this->request(['text' => '  ', 'langcode' => 'fi']))->getStatusCode());

    $tooLong = str_repeat('ä', TextServicesGenerator::MAX_CONTENT_LENGTH + 1);
    $this->assertSame(413, $this->controller->summary($this->request(['text' => $tooLong, 'langcode' => 'fi']))->getStatusCode());

    $this->assertNull($this->textServicesResponses->getLastRequest());
  }

}
