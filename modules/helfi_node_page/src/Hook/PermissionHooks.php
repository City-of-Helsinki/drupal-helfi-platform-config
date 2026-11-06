<?php

declare(strict_types=1);

namespace Drupal\helfi_node_page\Hook;

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
        'clone page content',
        'create page content',
        'delete any page content',
        'delete own page content',
        'delete page revisions',
        'edit any page content',
        'edit own page content',
        'revert page revisions',
        'set page published on date',
        'translate page node',
        'view page revisions',
      ],
      'content_producer' => [
        'clone page content',
        'create page content',
        'delete own page content',
        'edit any page content',
        'edit own page content',
        'revert page revisions',
        'set page published on date',
        'translate page node',
        'view any unpublished page content',
        'view page revisions',
      ],
      'editor' => [
        'clone page content',
        'create page content',
        'delete any page content',
        'delete own page content',
        'delete page revisions',
        'edit any page content',
        'edit own page content',
        'revert page revisions',
        'set page published on date',
        'translate page node',
        'view page revisions',
      ],
      'read_only' => [
        'view any unpublished page content',
      ],
    ];
  }

}
