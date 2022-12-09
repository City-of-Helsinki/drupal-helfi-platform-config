<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_media\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests helfi_media module.
 *
 * @group helfi_platform_config
 */
class MediaTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_media',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Make sure front page loads.
   */
  public function testFrontPage() : void {
    $this->drupalGet('<front>');
    $this->assertSession()->statusCodeEquals(200);
  }

}
