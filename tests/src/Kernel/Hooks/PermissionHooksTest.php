<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel\Hooks;

use Drupal\helfi_platform_config\ConfigUpdate\ConfigUpdaterInterface;
use Drupal\helfi_platform_config\Hook\PermissionHooks;
use Drupal\helfi_platform_config\MultisiteContentId;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests PermissionHooks.
 */
#[Group('helfi_platform_config')]
#[RunTestsInSeparateProcesses]
final class PermissionHooksTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'node',
    'filter',
    'language',
    'content_translation',
    'link',
    'diff',
    'helfi_api_base',
    'config_rewrite',
    'external_entities',
    'helfi_platform_config',
    'helfi_user_roles',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installConfig(['system', 'user', 'helfi_user_roles']);

    // The permissions granted by PermissionHooks only exist once the
    // helfi_multisite_content external entity type is installed; otherwise
    // Role::preSave() silently strips them as unrecognized permissions.
    $this->installConfig(['external_entities', 'helfi_platform_config']);
    $this->installEntitySchema(MultisiteContentId::ENTITY_TYPE_ID);
  }

  /**
   * Tests that the module's permissions get installed onto the roles.
   */
  #[Test]
  public function testPermissionsAreGranted(): void {
    $hook = new PermissionHooks();
    $permissions = $hook->permissions();

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
