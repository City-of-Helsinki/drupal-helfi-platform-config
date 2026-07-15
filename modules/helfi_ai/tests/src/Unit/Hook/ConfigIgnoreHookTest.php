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
   * The pattern is ignored for update and delete.
   */
  public function testAddsPromptPatternExceptImportCreate(): void {
    $ignored = new ConfigIgnoreConfig('simple', []);
    (new ConfigIgnoreHook())->configIgnoreIgnoredAlter($ignored);
    $pattern = 'ai.ai_prompt.helfi_*';

    $this->assertContains($pattern, $ignored->getList('import', 'update'));
    $this->assertContains($pattern, $ignored->getList('import', 'delete'));
    $this->assertContains($pattern, $ignored->getList('export', 'update'));
    $this->assertContains($pattern, $ignored->getList('export', 'delete'));
    $this->assertNotContains($pattern, $ignored->getList('import', 'create'));
    $this->assertNotContains($pattern, $ignored->getList('export', 'create'));
  }

}
