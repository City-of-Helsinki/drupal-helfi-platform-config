<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ai\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\helfi_ai\Service\AiGenerator;
use Drupal\language\Entity\ConfigurableLanguage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests tone checking through the echoai test provider.
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
   * The generator under test.
   */
  private AiGenerator $generator;

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

    $this->generator = $this->container->get(AiGenerator::class);
  }

  /**
   * Tests that content reaches the provider and a suggestion is returned.
   */
  public function testSuggestsRewrite(): void {
    $content = '<p>Tone kernel content ' . $this->randomMachineName() . '</p>';

    $suggestion = $this->generator->checkTone($content, 'en');

    $this->assertNotNull($suggestion);
    $this->assertStringContainsString($content, $suggestion);
  }

  /**
   * Tests that the content language prompt translation is used.
   */
  public function testUsesLanguageSpecificPrompt(): void {
    ConfigurableLanguage::createFromLangcode('fi')->save();
    // Translate the prompt for Finnish via a language config override.
    $this->container->get('language.config_factory_override')
      ->getOverride('fi', self::PROMPT)
      ->set('prompt', 'FINNISH TONE GUIDANCE {content}')
      ->save();

    $suggestion = $this->generator->checkTone('<p>x</p>', 'fi');

    $this->assertNotNull($suggestion);
    $this->assertStringContainsString('FINNISH TONE GUIDANCE', $suggestion);
  }

  /**
   * Tests that a missing prompt returns null.
   */
  public function testReturnsNullWhenPromptMissing(): void {
    $this->config(self::PROMPT)->delete();

    $this->assertNull($this->generator->checkTone('<p>x</p>', 'en'));
  }

  /**
   * Tests that an unavailable provider returns null.
   */
  public function testReturnsNullWhenProviderUnavailable(): void {
    $this->config('ai.settings')
      ->set('default_providers', [
        'chat' => ['provider_id' => 'no_such_provider', 'model_id' => 'test'],
      ])
      ->save();

    $this->assertNull($this->generator->checkTone('<p>x</p>', 'en'));
  }

}
