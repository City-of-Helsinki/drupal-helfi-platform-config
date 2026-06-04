<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_csp\Unit;

use Drupal\helfi_csp\Hook\ConfigIgnoreHook;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for ConfigIgnoreHook.
 */
#[Group('helfi_csp')]
#[CoversClass(ConfigIgnoreHook::class)]
class ConfigIgnoreHookTest extends UnitTestCase {

  /**
   * Tests that helfi_csp.settings is added to the ignore settings.
   */
  public function testAddsHelfiCspSettings(): void {
    $settings = ['some.other.config'];
    (new ConfigIgnoreHook())->configIgnoreSettingsAlter($settings);
    $this->assertSame(['some.other.config', 'helfi_csp.settings'], $settings);
  }

}
