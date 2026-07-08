<?php

declare(strict_types=1);

namespace Drupal\helfi_ai\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Permission hook implementations for HELfi AI.
 */
class PermissionsHooks {

  /**
   * Implements hook_platform_config_grant_permissions().
   *
   * @return array<string, string[]>
   *   Permissions to grant, keyed by role ID.
   */
  #[Hook('platform_config_grant_permissions')]
  public function grantPermissions(): array {
    $permissions = [
      'use helfi ai title suggestion',
      'use helfi ai tone check',
    ];
    return [
      'admin' => $permissions,
      'editor' => $permissions,
      'content_producer' => $permissions,
    ];
  }

}
