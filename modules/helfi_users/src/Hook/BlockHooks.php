<?php

declare(strict_types=1);

namespace Drupal\helfi_users\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for helfi_users module.
 */
class BlockHooks {

  /**
   * Implements hook_block_alter().
   *
   * @phpstan-param array<mixed> $definitions
   */
  #[Hook('block_alter')]
  public function localTasksBlockAlter(array &$definitions): void {
    if (isset($definitions['local_tasks_block'])) {
      $definitions['local_tasks_block']['class'] = 'Drupal\helfi_users\Plugin\Block\LocalTasksBlock';
    }
  }

}
