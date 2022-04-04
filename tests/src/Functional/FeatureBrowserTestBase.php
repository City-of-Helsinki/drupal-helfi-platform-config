<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_platform_config\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Browser test base for features.
 */
abstract class FeatureBrowserTestBase extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_platform_config',
    'update_helper',
    'helfi_api_base',
    'menu_block_current_language',
  ];

}
