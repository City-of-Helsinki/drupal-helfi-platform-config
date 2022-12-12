<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_ckeditor\Functional;

use Drupal\Tests\helfi_platform_config\Functional\BrowserTestBase;

/**
 * Tests helfi_ckeditor module.
 *
 * @group helfi_platform_config
 */
class CkeditorTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_ckeditor',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Make sure front page is accessible.
   */
  public function test() : void {
    $this->drupalGet('<front>');
  }

}
