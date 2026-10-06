<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Permission hooks.
 *
 * @codeCoverageIgnore
 */
final readonly class PermissionHooks {

  public const array MULTISITE_PERMISSIONS = [
    'view helfi_multisite_content external entity',
  ];

  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * Implements hook_platform_config_grant_permissions().
   *
   * @return array<string, array<int, string>>
   *   The permissions.
   */
  #[Hook(hook: 'platform_config_grant_permissions')]
  public function permissions(): array {
    $permissions = [];
    foreach ([
      'admin',
      'content_producer',
      'editor',
    ] as $role) {
      // Skip if role does not exist.
      if (!$this->entityTypeManager->getStorage('user_role')->load($role)) {
        continue;
      }
      $permissions[$role] = self::MULTISITE_PERMISSIONS;
    }
    return $permissions;
  }

}
