<?php

declare(strict_types=1);

namespace Drupal\helfi_node_landing_page\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\helfi_platform_config\DTO\ChangedAtFieldBundle;

/**
 * Hook implementations for the reusable 'changed_at' field.
 */
class ChangedAtFieldHooks {

  /**
   * Implements hook_helfi_changed_at_field_bundles().
   *
   * Opts landing_page nodes into the reusable 'changed_at' field provided by
   * helfi_platform_config.
   *
   * @return array<\Drupal\helfi_platform_config\DTO\ChangedAtFieldBundle>
   *   The entity type/bundle combinations to opt in.
   */
  #[Hook('helfi_changed_at_field_bundles')]
  public static function changedAtFieldBundles(): array {
    return [
      new ChangedAtFieldBundle('node', 'landing_page'),
    ];
  }

}
