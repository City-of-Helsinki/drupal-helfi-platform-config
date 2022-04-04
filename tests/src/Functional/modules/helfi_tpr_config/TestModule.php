<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_platform_config\Functional\modules\helfi_tpr_config;

use Drupal\Tests\helfi_platform_config\Functional\FeatureBrowserTestBase;

/**
 * @group helfi_platform_config
 */
final class TestModule extends FeatureBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_tpr',
    'helfi_tpr_config'
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  public function testEnable() : void {
    $this->assertTrue(TRUE);
  }

}
