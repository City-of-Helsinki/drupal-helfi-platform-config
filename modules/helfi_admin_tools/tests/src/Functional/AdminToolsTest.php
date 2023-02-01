<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_admin_tools\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests Helfi admin tools module.
 */
class AdminToolsTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_admin_tools',
    'taxonomy',
    'block',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalPlaceBlock('page_title_block');
  }

  /**
   * Test Helfi Admin tools routes.
   */
  public function testAdminToolsRoutes() : void {
    $routes = [
      'helfi_admin_tools.list_all' => 'Tools',
      'helfi_admin_tools.site_settings_form' => 'Site settings',
      'helfi_admin_tools.taxonomy' => 'Taxonomy',
    ];

    // Test as user without proper permissions.
    $authenticated_user = $this->drupalCreateUser([]);
    $this->drupalLogin($authenticated_user);

    foreach ($routes as $route => $title) {
      $this->drupalGet(Url::fromRoute($route));
      $this->assertSession()->statusCodeEquals(403);
    }

    // Test as user with proper permissions.
    $this->drupalLogin($this->drupalCreateUser([
      'access administration pages',
      'access taxonomy overview',
    ]));

    foreach ($routes as $route => $title) {
      $this->drupalGet(Url::fromRoute($route));
      $this->assertSession()->statusCodeEquals(200);
      if (!empty($title)) {
        $this->assertSession()->pageTextContains($title);
      }
    }
  }

}
