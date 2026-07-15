<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_curated_event_list\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Permission hooks.
 */
final class PermissionHooks {

  /**
   * Implements hook_platform_config_grant_permissions().
   *
   * @return array<string, array<int, string>>
   *   The permissions.
   */
  #[Hook(hook: 'platform_config_grant_permissions')]
  public function permissions(): array {
    $permissions = [
      // These permissions are required for the Curated Events paragraph widget.
      // Without them, the entity reference field displays a "Restricted access"
      // message.
      'update linkedevents_event external entity',
      'view linkedevents_event external entity',
      'view linkedevents_event external entity collection',
    ];
    return [
      'admin' => $permissions,
      'content_producer' => $permissions,
      'editor' => $permissions,
    ];
  }

}
