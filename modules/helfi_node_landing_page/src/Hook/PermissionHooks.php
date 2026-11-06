<?php

declare(strict_types=1);

namespace Drupal\helfi_node_landing_page\Hook;

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
        'clone landing_page content',
        'create landing_page content',
        'delete any landing_page content',
        'delete landing_page revisions',
        'delete own landing_page content',
        'edit any landing_page content',
        'edit own landing_page content',
        'revert landing_page revisions',
        'set landing_page published on date',
        'translate landing_page node',
        'view landing_page revisions',
      ],
      'content_producer' => [
        'clone landing_page content',
        'create landing_page content',
        'delete own landing_page content',
        'edit any landing_page content',
        'edit own landing_page content',
        'revert landing_page revisions',
        'set landing_page published on date',
        'translate landing_page node',
        'view any unpublished landing_page content',
        'view landing_page revisions',
      ],
      'editor' => [
        'clone landing_page content',
        'create landing_page content',
        'delete any landing_page content',
        'delete landing_page revisions',
        'delete own landing_page content',
        'edit any landing_page content',
        'edit own landing_page content',
        'revert landing_page revisions',
        'set landing_page published on date',
        'translate landing_page node',
        'view landing_page revisions',
      ],
      'read_only' => [
        'view any unpublished landing_page content',
      ],
    ];
  }

}
