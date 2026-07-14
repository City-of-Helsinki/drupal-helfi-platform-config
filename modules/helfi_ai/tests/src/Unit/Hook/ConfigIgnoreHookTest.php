<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ai\Unit\Hook;

use Drupal\config_ignore\ConfigIgnoreConfig;
use Drupal\helfi_ai\Hook\ConfigIgnoreHook;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that the AI prompt configs are protected from config reverts.
 */
#[Group('helfi_ai')]
#[CoversClass(ConfigIgnoreHook::class)]
class ConfigIgnoreHookTest extends UnitTestCase {

  /**
   * The AI prompt pattern the hook adds.
   */
  private const PATTERN = 'ai.ai_prompt.helfi_*';

  /**
   * The pattern is ignored for every operation except import create.
   */
  public function testAddsPromptPatternExceptImportCreate(): void {
    $ignored = new ConfigIgnoreConfig('simple', []);
    (new ConfigIgnoreHook())->configIgnoreIgnoredAlter($ignored);

    $this->assertNotContains(self::PATTERN, $ignored->getList('import', 'create'));
    $this->assertContains(self::PATTERN, $ignored->getList('import', 'update'));
    $this->assertContains(self::PATTERN, $ignored->getList('import', 'delete'));
    $this->assertContains(self::PATTERN, $ignored->getList('export', 'create'));
    $this->assertContains(self::PATTERN, $ignored->getList('export', 'update'));
    $this->assertContains(self::PATTERN, $ignored->getList('export', 'delete'));
  }

  /**
   * Existing ignored patterns are kept alongside the added pattern.
   */
  public function testPreservesExistingPatterns(): void {
    $ignored = new ConfigIgnoreConfig('simple', ['some.other.setting']);
    (new ConfigIgnoreHook())->configIgnoreIgnoredAlter($ignored);

    $this->assertContains('some.other.setting', $ignored->getList('import', 'update'));
    $this->assertContains(self::PATTERN, $ignored->getList('import', 'update'));
  }

}
