<?php

declare(strict_types=1);

namespace Drupal\helfi_llms_txt\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Config ignore hook.
 */
final readonly class ConfigIgnoreHook {

  /**
   * Implements hook_config_ignore_settings_alter().
   *
   * @phpstan-param array<int, string> $settings
   */
  #[Hook('config_ignore_settings_alter')]
  public function configIgnoreSettingsAlter(array &$settings): void {
    $settings[] = 'helfi_llms_txt.settings:content';
  }

}
