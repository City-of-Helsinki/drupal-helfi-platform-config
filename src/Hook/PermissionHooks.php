<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Permission hooks.
 */
final readonly class PermissionHooks {

  public const array MULTISITE_PERMISSIONS = [
    'view helfi_multisite_content external entity',
  ];

  /**
   * Implements hook_platform_config_grant_permissions().
   *
   * @return array<string, array<int, string>>
   *   The permissions.
   */
  #[Hook(hook: 'platform_config_grant_permissions')]
  public function permissions(): array {
    return [
      'admin' => self::MULTISITE_PERMISSIONS,
      'content_producer' => self::MULTISITE_PERMISSIONS,
      'editor' => self::MULTISITE_PERMISSIONS,
    ];
  }

}
