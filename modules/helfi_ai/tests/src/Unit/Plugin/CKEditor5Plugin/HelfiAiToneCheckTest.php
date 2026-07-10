<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ai\Unit\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginDefinition;
use Drupal\Core\Access\CsrfRequestHeaderAccessCheck;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\GeneratedUrl;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\editor\EditorInterface;
use Drupal\helfi_ai\Plugin\CKEditor5Plugin\AiToneCheck;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Prophecy\Argument;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tests the tone-check CKEditor 5 plugin dynamic configuration.
 */
#[Group('helfi_ai')]
#[CoversClass(AiToneCheck::class)]
class HelfiAiToneCheckTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    parent::tearDown();
    // Reset the container so it does not leak into other tests.
    \Drupal::setContainer($this->createMock(ContainerInterface::class));
  }

  /**
   * Builds the plugin with a container whose url_generator returns $endpoint.
   *
   * @param string $endpoint
   *   The URL the mocked generator should return for the tone-check route.
   * @param string $langcode
   *   The current language id the plugin should read.
   *
   * @return \Drupal\helfi_ai\Plugin\CKEditor5Plugin\AiToneCheck
   *   The plugin under test.
   */
  private function createPlugin(string $endpoint, string $langcode): AiToneCheck {
    // Url::fromRoute(...)->toString(TRUE) resolves through the url_generator.
    $urlGenerator = $this->prophesize(UrlGeneratorInterface::class);
    $urlGenerator->generateFromRoute('helfi_ai.tone_check', Argument::cetera())
      ->willReturn((new GeneratedUrl())->setGeneratedUrl($endpoint));
    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')->willReturnCallback(
      fn(string $id): object => match ($id) {
        'url_generator' => $urlGenerator->reveal(),
        default => throw new \RuntimeException('Unexpected service: ' . $id),
      }
    );
    \Drupal::setContainer($container);

    $csrf = $this->prophesize(CsrfTokenGenerator::class);
    $csrf->get(CsrfRequestHeaderAccessCheck::TOKEN_KEY)->willReturn('test-token');

    $language = $this->prophesize(LanguageInterface::class);
    $language->getId()->willReturn($langcode);
    $languageManager = $this->prophesize(LanguageManagerInterface::class);
    $languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->willReturn($language->reveal());

    return new AiToneCheck(
      [],
      'helfi_ai_tone_check',
      new CKEditor5PluginDefinition([]),
      $csrf->reveal(),
      $languageManager->reveal(),
    );
  }

  /**
   * The dynamic config carries the endpoint, CSRF token and current language.
   */
  public function testInjectsEndpointTokenAndLangcode(): void {
    $plugin = $this->createPlugin('/en/helfi-ai/tone-check', 'en');

    $config = $plugin->getDynamicPluginConfig([], $this->createMock(EditorInterface::class));

    $this->assertArrayHasKey('helfiAiToneCheck', $config);
    $this->assertSame('/en/helfi-ai/tone-check', $config['helfiAiToneCheck']['endpoint']);
    $this->assertSame('test-token', $config['helfiAiToneCheck']['csrfToken']);
    $this->assertSame('en', $config['helfiAiToneCheck']['langcode']);
  }

  /**
   * Existing static plugin config is preserved alongside the injected values.
   */
  public function testPreservesExistingStaticConfig(): void {
    $plugin = $this->createPlugin('/sv/helfi-ai/tone-check', 'sv');

    $config = $plugin->getDynamicPluginConfig(
      ['existing' => ['keep' => TRUE]],
      $this->createMock(EditorInterface::class),
    );

    $this->assertSame(['keep' => TRUE], $config['existing']);
    $this->assertSame('sv', $config['helfiAiToneCheck']['langcode']);
  }

}
