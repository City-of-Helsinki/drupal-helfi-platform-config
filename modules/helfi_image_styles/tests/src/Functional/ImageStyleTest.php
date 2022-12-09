<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_image_styles\Functional;

use Drupal\Tests\helfi_platform_config\Functional\BrowserTestBase;

/**
 * Tests helfi_image_styles module.
 *
 * @group helfi_platform_config
 */
class ImageStyleTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_image_styles',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests landing page content type.
   */
  public function testImageStyles() : void {
    $this->drupalGet('<front>');
  }

}
