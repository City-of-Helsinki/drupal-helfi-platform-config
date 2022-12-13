<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_node_announcement\Functional;

use Drupal\Tests\helfi_platform_config\Functional\BrowserTestBase;

/**
 * Tests helfi_node_announcement module.
 *
 * @group helfi_platform_config
 */
class AnnouncementTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_base_content',
    'helfi_node_announcement',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests page content type.
   */
  public function testDefault() : void {
    $this->assertFrontPageLanguages();
  }

}
