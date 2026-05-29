<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_users\Kernel;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Menu\LocalTaskManagerInterface;
use Drupal\helfi_users\Plugin\Block\LocalTasksBlock;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests for LocalTasksBlock.
 *
 * @group helfi_users
 */
class LocalTasksBlockTest extends KernelTestBase {

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
   * Tests early return when there are no primary tasks.
   */
  public function testBuildReturnsEarlyWithoutPrimaryTasks(): void {
    $build = $this->createBlockWithPrimary([])->build();
    $this->assertArrayNotHasKey('#primary', $build);
  }

  /**
   * Tests that the scheduler user-page tab is removed.
   */
  public function testScheduledContentLinkIsRemoved(): void {
    $schedulerRoute = 'views_view:view.scheduler_scheduled_content.user_page';
    $block = $this->createBlockWithPrimary([
      $schedulerRoute => ['#link' => ['title' => 'Scheduled']],
      'entity.user.edit_form' => ['#link' => ['title' => 'Edit']],
    ]);

    $build = $block->build();
    $this->assertArrayNotHasKey($schedulerRoute, $build['#primary']);
    $this->assertArrayHasKey('entity.user.edit_form', $build['#primary']);
  }

  /**
   * Tests that the user canonical tab is renamed to "My content".
   */
  public function testUserCanonicalRenamedToMyContent(): void {
    $block = $this->createBlockWithPrimary([
      'entity.user.canonical' => ['#link' => ['title' => 'View']],
      'entity.user.edit_form' => ['#link' => ['title' => 'Edit']],
    ]);

    $build = $block->build();
    $this->assertEquals(
      'My content',
      (string) $build['#primary']['entity.user.canonical']['#link']['title'],
    );
  }

  /**
   * Creates a LocalTasksBlock instance.
   *
   * @param array<string, mixed> $primaryTasks
   *   Tasks to return as the primary level from the mock.
   */
  private function createBlockWithPrimary(array $primaryTasks): LocalTasksBlock {
    $taskManager = $this->createMock(LocalTaskManagerInterface::class);
    $taskManager->method('getLocalTasks')
      ->willReturnCallback(function ($routeName, $level) use ($primaryTasks): array {
        return [
          'tabs' => $level === 0 ? $primaryTasks : [],
          'cacheability' => new CacheableMetadata(),
        ];
      });
    $this->container->set('plugin.manager.menu.local_task', $taskManager);

    return LocalTasksBlock::create($this->container, [], 'local_tasks_block', ['provider' => 'core']);
  }

}
