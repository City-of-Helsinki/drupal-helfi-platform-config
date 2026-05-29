<?php

declare(strict_types=1);

namespace Drupal\helfi_users\Hooks;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for helfi_users module.
 */
class BlockHooks {

  /**
   * Implements hook_block_alter().
   */
  #[Hook('block_alter')]
  public function localTasksBlockAlter(&$definitions) : void {
    if (isset($definitions['local_tasks_block'])) {
      $definitions['local_tasks_block']['class'] = 'Drupal\helfi_users\Plugin\Block\LocalTasksBlock';
    }
  }

}
