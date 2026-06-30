<?php

declare(strict_types=1);

namespace Drupal\helfi_ai\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Permission hook implementations for HELfi AI.
 */
class PermissionsHooks {

  /**
   * Implements hook_platform_config_grant_permissions().
   *
   * Grants the AI SEO title suggestion permission to the editorial roles that
   * create content. helfi_platform_config applies these on install and on every
   * config update (drush helfi:platform-config:update), so existing sites pick
   * the grant up on the next release without a manual step.
   *
   * @return array<string, string[]>
   *   Permissions to grant, keyed by role ID.
   */
  #[Hook('platform_config_grant_permissions')]
  public function grantPermissions(): array {
    $permissions = ['use helfi ai title suggestion'];
    return [
      'admin' => $permissions,
      'editor' => $permissions,
      'content_producer' => $permissions,
      'news_producer' => $permissions,
    ];
  }

}
