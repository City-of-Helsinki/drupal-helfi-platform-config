<?php

declare(strict_types=1);

namespace Drupal\helfi_csp\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Config ignore hook.
 */
final readonly class ConfigIgnoreHook {

  /**
   * Implements hook_config_ignore_settings_alter().
   *
   * Ignore helfi_csp settings to allow tuning the values used for cron based
   * logging of CSP violation reports to Sentry.
   *
   * @param array<int, string> $settings
   *   List of config_ignore patterns to amend.
   */
  #[Hook('config_ignore_settings_alter')]
  public function configIgnoreSettingsAlter(array &$settings): void {
    $ignored = [
      'helfi_csp.settings',
    ];
    foreach ($ignored as $entry) {
      if (!in_array($entry, $settings, TRUE)) {
        $settings[] = $entry;
      }
    }
  }

}
