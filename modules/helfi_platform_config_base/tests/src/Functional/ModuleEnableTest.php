<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config_base\Functional;

use Drupal\Tests\helfi_platform_config\Functional\BrowserTestBase;

/**
 * Tests that all modules can be enabled.
 *
 * @group helfi_platform_config
 */
class ModuleEnableTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_platform_config_base',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Make sure all modules can be enabled.
   */
  public function testEnable() : void {
    $this->assertFrontPageLanguages();
  }

}
