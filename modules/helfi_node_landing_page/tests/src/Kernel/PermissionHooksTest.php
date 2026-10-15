<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_node_landing_page\Kernel;

use Drupal\helfi_platform_config\ConfigUpdate\ConfigUpdaterInterface;
use Drupal\helfi_node_landing_page\Hook\PermissionHooks;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests PermissionHooks.
 */
#[Group('helfi_node_landing_page')]
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
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installConfig(['system', 'user', 'helfi_user_roles']);

    // The clone permission is generated per node type; otherwise
    // Role::preSave() silently strips it as an unrecognized permission.
    NodeType::create(['type' => 'landing_page', 'name' => 'landing_page'])->save();
  }

  /**
   * Tests that the clone permission is granted to content editing roles.
   */
  #[Test]
  public function testClonePermissionIsGranted(): void {
    $permissions = (new PermissionHooks())->permissions();

    $this->container
      ->get(ConfigUpdaterInterface::class)
      ->updatePermissions($permissions);

    foreach (['admin', 'content_producer', 'editor'] as $roleId) {
      $this->assertContains('clone landing_page content', $permissions[$roleId]);
      $this->assertTrue(Role::load($roleId)->hasPermission('clone landing_page content'));
    }
    $this->assertFalse(Role::load('read_only')->hasPermission('clone landing_page content'));
  }

}
