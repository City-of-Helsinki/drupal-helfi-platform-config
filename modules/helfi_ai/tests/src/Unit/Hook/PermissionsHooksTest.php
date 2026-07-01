<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ai\Unit\Hook;

use Drupal\helfi_ai\Hook\PermissionsHooks;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the editorial-role grants for the tone check permission.
 */
#[Group('helfi_ai')]
#[CoversClass(PermissionsHooks::class)]
class PermissionsHooksTest extends UnitTestCase {

  /**
   * The grant hook returns the permission for each guaranteed base role.
   */
  public function testGrantsToneCheckPermissionToEditorialRoles(): void {
    $permissions = (new PermissionsHooks())->grantPermissions();

    foreach (['admin', 'editor', 'content_producer'] as $role) {
      $this->assertArrayHasKey($role, $permissions);
      $this->assertSame(['use helfi ai tone check'], $permissions[$role]);
    }
    // Instance-specific roles must not be granted (they do not exist on every
    // site and would fail the grant on a fresh install).
    $this->assertArrayNotHasKey('news_producer', $permissions);
  }

}
