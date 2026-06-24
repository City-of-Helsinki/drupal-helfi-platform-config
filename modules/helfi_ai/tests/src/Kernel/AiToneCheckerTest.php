<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ai\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\helfi_ai\Service\AiToneChecker;
use Drupal\language\Entity\ConfigurableLanguage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests tone checking through the real AI provider stack.
 *
 * Runs the checker against the AI module's echoai test provider, so the chain
 * is exercised for real (per-language prompt loading, provider resolution, the
 * chat call) without any external service or API key. The echoai provider
 * echoes the prompt back, which lets the test assert what reached the provider.
 */
#[Group('helfi_ai')]
#[RunTestsInSeparateProcesses]
class AiToneCheckerTest extends EntityKernelTestBase {

  /**
   * Config name of the tone-check prompt.
   */
  private const PROMPT = 'ai.ai_prompt.helfi_tone_check__helfi_tone_check_default';

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
   * The tone checker under test.
   */
  private AiToneChecker $checker;

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

    $this->checker = $this->container->get(AiToneChecker::class);
  }

  /**
   * Content is sent to the provider and a rewrite suggestion is returned.
   */
  public function testSuggestsRewrite(): void {
    $content = '<p>Tone kernel content ' . $this->randomMachineName() . '</p>';

    $suggestion = $this->checker->check($content, 'en');

    $this->assertNotNull($suggestion);
    // The echoai provider echoes the prompt, so the editor content has reached
    // the provider through the {content} placeholder.
    $this->assertStringContainsString($content, $suggestion);
  }

  /**
   * The language-specific prompt translation is used for the content language.
   */
  public function testUsesLanguageSpecificPrompt(): void {
    ConfigurableLanguage::createFromLangcode('fi')->save();
    // Translate the prompt for Finnish via a language config override.
    $this->container->get('language.config_factory_override')
      ->getOverride('fi', self::PROMPT)
      ->set('prompt', 'FINNISH TONE GUIDANCE {content}')
      ->save();

    $suggestion = $this->checker->check('<p>x</p>', 'fi');

    $this->assertNotNull($suggestion);
    // The echoed prompt reveals that the Finnish translation was used.
    $this->assertStringContainsString('FINNISH TONE GUIDANCE', $suggestion);
  }

  /**
   * Empty content short-circuits to NULL without calling the provider.
   */
  public function testReturnsNullForEmptyContent(): void {
    $this->assertNull($this->checker->check('   ', 'en'));
  }

  /**
   * A missing prompt makes the check fail gracefully (NULL).
   */
  public function testReturnsNullWhenPromptMissing(): void {
    $this->config(self::PROMPT)->delete();

    $this->assertNull($this->checker->check('<p>x</p>', 'en'));
  }

  /**
   * An unresolvable provider makes the check fail gracefully (NULL).
   */
  public function testReturnsNullWhenProviderUnavailable(): void {
    $this->config('ai.settings')
      ->set('default_providers', [
        'chat' => ['provider_id' => 'no_such_provider', 'model_id' => 'test'],
      ])
      ->save();

    $this->assertNull($this->checker->check('<p>x</p>', 'en'));
  }

}
