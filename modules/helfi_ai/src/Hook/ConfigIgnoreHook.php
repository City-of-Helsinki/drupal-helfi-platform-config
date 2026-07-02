<?php

declare(strict_types=1);

namespace Drupal\helfi_ai\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Config ignore hook.
 */
final readonly class ConfigIgnoreHook {

  /**
   * Implements hook_config_ignore_settings_alter().
   *
   * The shipped prompts are initial defaults only; editors fine-tune them on
   * the running site. Ignore them so `drush config:import` does not revert
   * those edits on deploy. (Module updates never touch them anyway, since
   * config/install is applied only at install time.)
   *
   * The pattern carries no `collection|` prefix, so config_ignore applies it to
   * every collection: this protects the tone-check prompt and its per-language
   * translations (the `language.*` config overrides), which matters because the
   * prompt is authored natively per language.
   *
   * @param array<int, string> $settings
   *   List of config_ignore patterns to amend.
   */
  #[Hook('config_ignore_settings_alter')]
  public function configIgnoreSettingsAlter(array &$settings): void {
    $ignored = [
      'ai.ai_prompt.helfi_tone_check__helfi_tone_check_default',
    ];
    foreach ($ignored as $entry) {
      if (!in_array($entry, $settings, TRUE)) {
        $settings[] = $entry;
      }
    }
  }

}
