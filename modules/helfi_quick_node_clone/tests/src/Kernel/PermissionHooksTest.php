<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_quick_node_clone\Kernel;

use Drupal\helfi_platform_config\ConfigUpdate\ConfigUpdaterInterface;
use Drupal\helfi_quick_node_clone\Hook\PermissionHooks;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests PermissionHooks.
 */
#[Group('helfi_quick_node_clone')]
class PermissionHooksTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'text',
    'helfi_user_roles',
    'diff',
    'helfi_api_base',
    'config_rewrite',
    'language',
    'content_translation',
    'helfi_platform_config',
    'quick_node_clone',
    'helfi_quick_node_clone',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installConfig(['system', 'user', 'helfi_user_roles']);

    // The clone permissions are generated per node type; otherwise
    // Role::preSave() silently strips them as unrecognized permissions.
    foreach (['landing_page', 'page'] as $type) {
      NodeType::create(['type' => $type, 'name' => $type])->save();
    }
  }

  /**
   * Tests that the module's permissions get installed onto the roles.
   */
  #[Test]
  public function testPermissionsAreGranted(): void {
    $permissions = (new PermissionHooks())->permissions();

    $this->assertEqualsCanonicalizing(
      ['admin', 'content_producer', 'editor'],
      array_keys($permissions),
    );

    $this->container
      ->get(ConfigUpdaterInterface::class)
      ->updatePermissions($permissions);

    foreach ($permissions as $roleId => $list) {
      $role = Role::load($roleId);
      $this->assertNotNull($role);

      foreach ($list as $permission) {
        $this->assertTrue($role->hasPermission($permission));
      }
    }
  }

}
