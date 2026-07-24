<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_paragraphs_curated_event_list\Kernel;

use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\helfi_paragraphs_curated_event_list\Hook\PermissionHooks;
use Drupal\helfi_platform_config\ConfigUpdate\ConfigUpdaterInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests PermissionHooks.
 */
#[Group('helfi_paragraphs_curated_event_list')]
class PermissionHooksTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'helfi_user_roles',
    'helfi_api_base',
    'config_rewrite',
    'language',
    'content_translation',
    'helfi_platform_config',
    'entity_reference_revisions',
    'field',
    'file',
    'paragraphs',
    'linkit',
    'breakpoint',
    'responsive_image',
    'link',
    'datetime',
    'imagecache_external',
    'external_entities',
    'text',
    'helfi_paragraphs_curated_event_list',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Triggers rebuilding routes.
    // @see https://www.drupal.org/project/external_entities/issues/3549828.
    $this->container
      ->get(RouteProviderInterface::class)
      ->getAllRoutes();

    $this->installEntitySchema('user');
    $this->installConfig(['system', 'user', 'helfi_user_roles']);

    // The permissions granted by PermissionHooks only exist once the
    // linkedevents_event external entity type is installed; otherwise
    // Role::preSave() silently strips them as unrecognized permissions.
    $this->installConfig(['external_entities', 'helfi_paragraphs_curated_event_list']);
    $this->installEntitySchema('linkedevents_event');
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
