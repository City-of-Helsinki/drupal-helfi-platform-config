<?php

declare(strict_types=1);

namespace Drupal\hdbt_admin_tools\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Permission hook implementations for HDBT Admin tools.
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
    return [
      'admin' => [
        'administer site configuration',
      ],
    ];
  }

}
