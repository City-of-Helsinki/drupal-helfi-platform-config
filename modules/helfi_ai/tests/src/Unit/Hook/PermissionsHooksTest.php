<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ai\Unit\Hook;

use Drupal\helfi_ai\Hook\PermissionsHooks;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the AI functionality permissions.
 */
#[Group('helfi_ai')]
#[CoversClass(PermissionsHooks::class)]
class PermissionsHooksTest extends UnitTestCase {

  /**
   * Test the permissions hook.
   */
  public function testPermissions(): void {
    $permissions = (new PermissionsHooks())->grantPermissions();

    foreach (['admin', 'editor', 'content_producer'] as $role) {
      $this->assertArrayHasKey($role, $permissions);
      $this->assertSame([
        'use helfi ai title suggestion',
        'use helfi ai tone check',
      ], $permissions[$role]);
    }
  }

}
