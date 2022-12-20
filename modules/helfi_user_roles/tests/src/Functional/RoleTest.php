<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_user_roles\Functional;

use Drupal\Tests\helfi_platform_config\Functional\BrowserTestBase;

/**
 * Tests helfi_user_roles module.
 *
 * @group helfi_platform_config
 */
class RoleTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_user_roles',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests roles.
   */
  public function testRoles() : void {
    $this->drupalGet('<front>');
    $this->assertSession()->statusCodeEquals(200);
  }

}
