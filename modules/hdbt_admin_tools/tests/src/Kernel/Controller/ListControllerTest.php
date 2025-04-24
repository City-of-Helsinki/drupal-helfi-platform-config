<?php

declare(strict_types=1);

namespace Drupal\Tests\hdbt_admin_tools\Kernel\Controller;

use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests Helfi admin tools module.
 */
class ListControllerTest extends KernelTestBase {

  use ApiTestTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'hdbt_admin_tools',
    'taxonomy',
    'block',
    'language',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    // Create an empty user to make sure we don't accidentally get
    // uid 1 user with all permissions.
    $this->createUser();
  }

  /**
   * Test Helfi Admin tools routes.
   */
  public function testAdminToolsRoutes(): void {
    $routes = [
      'hdbt_admin_tools.list_all',
      'hdbt_admin_tools.site_settings_form',
      'hdbt_admin_tools.taxonomy',
    ];

    // Test as user without proper permissions.
    $user = $this->createUser([]);
    $this->setCurrentUser($user);

    foreach ($routes as $route) {
      $request = $this->getMockedRequest(Url::fromRoute($route)->toString());
      $response = $this->processRequest($request);
      $this->assertEquals(403, $response->getStatusCode());
    }

    // Test as user with proper permissions.
    $user = $this->createUser([
      'access administration pages',
      'access taxonomy overview',
    ]);
    $this->setCurrentUser($user);

    foreach ($routes as $route) {
      $request = $this->getMockedRequest(Url::fromRoute($route)->toString());
      $response = $this->processRequest($request);
      $this->assertEquals(200, $response->getStatusCode());
    }
  }

}
