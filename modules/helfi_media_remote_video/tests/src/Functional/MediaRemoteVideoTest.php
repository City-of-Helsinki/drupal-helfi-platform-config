<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_media_remote_video\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests helfi_media_remote_video module.
 *
 * @group helfi_platform_config
 */
class MediaRemoteVideoTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_media_remote_video',
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
