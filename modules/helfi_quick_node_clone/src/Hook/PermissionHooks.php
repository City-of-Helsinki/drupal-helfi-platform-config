<?php

declare(strict_types=1);

namespace Drupal\helfi_quick_node_clone\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Permission hook implementations for HELfi Quick node clone.
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
    $permissions = [
      'clone landing_page content',
      'clone page content',
    ];

    return [
      'admin' => $permissions,
      'content_producer' => $permissions,
      'editor' => $permissions,
    ];
  }

}
