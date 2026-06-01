<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_users\Kernel;

use Drupal\Core\Menu\Plugin\Block\LocalTasksBlock as CoreLocalTasksBlock;
use Drupal\helfi_users\Hook\BlockHooks;
use Drupal\helfi_users\Plugin\Block\LocalTasksBlock;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests for BlockHooks.
 *
 * @group helfi_users
 */
class BlockHooksTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'system',
    'user',
    'helfi_users',
  ];

  /**
   * Tests that local_tasks_block is replaced and other blocks are left alone.
   */
  public function testLocalTasksBlockClassIsOverridden(): void {
    $definitions = [
      'local_tasks_block' => ['class' => CoreLocalTasksBlock::class],
      'some_other_block' => ['class' => 'Drupal\block\Plugin\Block\Broken'],
    ];

    $hooks = new BlockHooks();
    $hooks->localTasksBlockAlter($definitions);

    $this->assertEquals(LocalTasksBlock::class, $definitions['local_tasks_block']['class']);
    $this->assertEquals('Drupal\block\Plugin\Block\Broken', $definitions['some_other_block']['class']);
  }

}
