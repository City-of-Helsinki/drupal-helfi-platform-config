<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations.
 */
final class PlatformConfigHooks {

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
        'administer helfi_search',
      ],
    ];
  }

}
