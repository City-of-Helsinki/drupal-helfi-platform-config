<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ai\Unit\Hook;

use Drupal\helfi_ai\Hook\PermissionsHooks;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the editorial-role grants for the title suggestion permission.
 */
#[Group('helfi_ai')]
#[CoversClass(PermissionsHooks::class)]
class PermissionsHooksTest extends UnitTestCase {

  /**
   * The grant hook returns the permission for each editorial role.
   */
  public function testGrantsSuggestionPermissionToEditorialRoles(): void {
    $permissions = (new PermissionsHooks())->grantPermissions();

    foreach (['admin', 'editor', 'content_producer', 'news_producer'] as $role) {
      $this->assertArrayHasKey($role, $permissions);
      $this->assertSame(['use helfi ai title suggestion'], $permissions[$role]);
    }
  }

}
