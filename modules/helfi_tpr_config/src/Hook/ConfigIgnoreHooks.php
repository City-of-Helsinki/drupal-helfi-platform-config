<?php

declare(strict_types=1);

namespace Drupal\helfi_tpr_config\Hook;

use Drupal\config_ignore\ConfigIgnoreConfig;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Implements entity hooks.
 */
class ConfigIgnoreHooks {

  /**
   * Implements hook_config_ignore_ignored_alter().
   *
   * @param \Drupal\config_ignore\ConfigIgnoreConfig $ignored
   *   Ignored configuration.
   */
  #[Hook('config_ignore_ignored_alter')]
  public function configIgnoreIgnoredAlter(ConfigIgnoreConfig $ignored): void {
    $settingsToIgnore = [
      'content.lock.settings:types',
      'content.lock.settings:form_op_lock',
    ];

    // Ignore the content lock settings when exporting configurations.
    // Without this, the next site install will fail because of dependency
    // issue with helfi_tpr_config module.
    foreach (['create', 'update', 'delete'] as $operation) {
      $list = array_merge(
        $ignored->getList('export', $operation),
        $settingsToIgnore,
      );
      $ignored->setList('export', $operation, $list);
    }
  }

}
