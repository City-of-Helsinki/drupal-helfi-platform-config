<?php

declare(strict_types=1);

namespace Drupal\helfi_ai\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\config_ignore\ConfigIgnoreConfig;

/**
 * Config ignore hook.
 */
final readonly class ConfigIgnoreHook {

  /**
   * Implements hook_config_ignore_ignored_alter().
   *
   * @param \Drupal\config_ignore\ConfigIgnoreConfig $ignored
   *   Ignored configurations.
   */
  #[Hook('config_ignore_ignored_alter')]
  public function configIgnoreIgnoredAlter(ConfigIgnoreConfig $ignored): void {
    $ignoredConfiguration = 'ai.ai_prompt.helfi_*';

    // Let Drupal create the AI prompt configurations during import, but prevent
    // updating and deleting the configurations during import and export.
    foreach (['create', 'update', 'delete'] as $operation) {
      foreach (['import', 'export'] as $direction) {
        if ($direction === 'import' && $operation === 'create') {
          continue;
        }
        $list = $ignored->getList($direction, $operation);
        $list[] = $ignoredConfiguration;
        $ignored->setList($direction, $operation, $list);
      }
    }
  }

}
