<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ai\Unit\Hook;

use Drupal\helfi_ai\Hook\ConfigIgnoreHook;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that the tone check prompt is protected from config reverts.
 */
#[Group('helfi_ai')]
#[CoversClass(ConfigIgnoreHook::class)]
class ConfigIgnoreHookTest extends UnitTestCase {

  /**
   * The tone check prompt is added to the config_ignore patterns.
   */
  public function testAddsToneCheckPromptToIgnoredSettings(): void {
    $settings = [];
    (new ConfigIgnoreHook())->configIgnoreSettingsAlter($settings);

    $this->assertContains('ai.ai_prompt.helfi_tone_check__helfi_tone_check_default', $settings);
  }

  /**
   * An already-present pattern is not duplicated.
   */
  public function testDoesNotDuplicateExistingPattern(): void {
    $settings = ['ai.ai_prompt.helfi_tone_check__helfi_tone_check_default'];
    (new ConfigIgnoreHook())->configIgnoreSettingsAlter($settings);

    $this->assertSame(
      ['ai.ai_prompt.helfi_tone_check__helfi_tone_check_default'],
      $settings,
    );
  }

}
