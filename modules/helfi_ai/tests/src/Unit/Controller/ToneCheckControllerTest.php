<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ai\Unit\Controller;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\helfi_ai\Controller\ToneCheckController;
use Drupal\helfi_ai\Service\AiToneChecker;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Prophecy\Argument;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the tone-check controller endpoint.
 */
#[Group('helfi_ai')]
#[CoversClass(ToneCheckController::class)]
class ToneCheckControllerTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    parent::tearDown();
    // Reset the container so it does not leak into other tests.
    \Drupal::setContainer($this->createMock(ContainerInterface::class));
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
  private function decode($response): array {
    return json_decode((string) $response->getContent(), TRUE);
  }

  /**
   * Valid content returns the checker's suggestion as JSON.
   */
  public function testReturnsSuggestionForValidContent(): void {
    $checker = $this->prophesize(AiToneChecker::class);
    $checker->check('<p>Hi</p>', 'en')->willReturn('<p>Hello</p>')->shouldBeCalledOnce();

    $response = (new ToneCheckController($checker->reveal()))
      ->check($this->request(['content' => '<p>Hi</p>', 'langcode' => 'en']));

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame(['suggestion' => '<p>Hello</p>'], $this->decode($response));
  }

  /**
   * Empty content is rejected with 400 without calling the checker.
   */
  public function testRejectsEmptyContent(): void {
    $checker = $this->prophesize(AiToneChecker::class);
    $checker->check(Argument::cetera())->shouldNotBeCalled();

    $response = (new ToneCheckController($checker->reveal()))
      ->check($this->request(['content' => '   ', 'langcode' => 'en']));

    $this->assertSame(400, $response->getStatusCode());
    $this->assertArrayHasKey('error', $this->decode($response));
  }

  /**
   * Content larger than the byte cap is rejected with 413.
   */
  public function testRejectsTooLargeContent(): void {
    $checker = $this->prophesize(AiToneChecker::class);
    $checker->check(Argument::cetera())->shouldNotBeCalled();

    $tooLarge = str_repeat('a', 256 * 1024 + 1);
    $response = (new ToneCheckController($checker->reveal()))
      ->check($this->request(['content' => $tooLarge, 'langcode' => 'en']));

    $this->assertSame(413, $response->getStatusCode());
  }

  /**
   * A NULL suggestion (provider failure) yields a 502.
   */
  public function testReturns502WhenCheckerFails(): void {
    $checker = $this->prophesize(AiToneChecker::class);
    $checker->check('<p>Hi</p>', 'en')->willReturn(NULL);

    $response = (new ToneCheckController($checker->reveal()))
      ->check($this->request(['content' => '<p>Hi</p>', 'langcode' => 'en']));

    $this->assertSame(502, $response->getStatusCode());
    $this->assertArrayHasKey('error', $this->decode($response));
  }

  /**
   * A missing langcode falls back to the current UI language.
   */
  public function testFallsBackToCurrentLanguageWhenLangcodeMissing(): void {
    $checker = $this->prophesize(AiToneChecker::class);
    $checker->check('<p>Hi</p>', 'sv')->willReturn('<p>Hej</p>')->shouldBeCalledOnce();

    $language = $this->prophesize(LanguageInterface::class);
    $language->getId()->willReturn('sv');
    $languageManager = $this->prophesize(LanguageManagerInterface::class);
    $languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->willReturn($language->reveal());

    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')->willReturnCallback(
      fn(string $id): object => match ($id) {
        'language_manager' => $languageManager->reveal(),
        default => throw new \RuntimeException('Unexpected service: ' . $id),
      }
    );
    \Drupal::setContainer($container);

    $response = (new ToneCheckController($checker->reveal()))
      ->check($this->request(['content' => '<p>Hi</p>']));

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame(['suggestion' => '<p>Hej</p>'], $this->decode($response));
  }

}
