<?php

declare(strict_types=1);

namespace Drupal\helfi_users\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Permission hooks.
 */
final readonly class PermissionHooks {

  /**
   * Implements hook_platform_config_grant_permissions().
   *
   * @return array<string, string[]>
   *   Permissions to grant, keyed by role ID.
   */
  #[Hook('platform_config_grant_permissions')]
  public function permissions(): array {
    return [
      'admin' => [
        'use favorites',
      ],
      'content_producer' => [
        'use favorites',
      ],
      'editor' => [
        'use favorites',
      ],
    ];
  }

}
