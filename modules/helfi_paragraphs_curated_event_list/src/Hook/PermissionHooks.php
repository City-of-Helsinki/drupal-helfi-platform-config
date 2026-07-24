<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_curated_event_list\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Permission hooks.
 */
final readonly class PermissionHooks {

  /**
   * The permissions required for the Curated Events paragraph widget.
   *
   * Without them, the entity reference field displays a "Restricted access"
   * message.
   */
  public const array PERMISSIONS = [
    'update linkedevents_event external entity',
    'view linkedevents_event external entity',
    'view linkedevents_event external entity collection',
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
      'admin' => self::PERMISSIONS,
      'content_producer' => self::PERMISSIONS,
      'editor' => self::PERMISSIONS,
    ];
  }

}
