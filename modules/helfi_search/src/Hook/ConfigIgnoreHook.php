<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Config ignore hook.
 */
final readonly class ConfigIgnoreHook {

  /**
   * Implements hook_config_ignore_settings_alter().
   *
   * Tuning values for the semantic-search algorithm should be tweakable on a
   * running site without `drush config:import` reverting them.
   *
   * @param array<int, string> $settings
   *   List of config_ignore patterns to amend.
   */
  #[Hook('config_ignore_settings_alter')]
  public function configIgnoreSettingsAlter(array &$settings): void {
    $ignored = [
      'helfi_search.settings:deboost_bundles',
      'helfi_search.settings:deboost_factor',
      'helfi_search.settings:min_score',
    ];
    foreach ($ignored as $entry) {
      if (!in_array($entry, $settings, TRUE)) {
        $settings[] = $entry;
      }
    }
  }

}
