<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_users\Kernel;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Menu\LocalTaskManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\helfi_users\Plugin\Block\LocalTasksBlock;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\ParameterBag;

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
   * Tests that no modifications are made when viewing another user's page.
   */
  public function testNoModificationsWhenViewingAnotherUsersPage(): void {
    $scheduleRoute = 'views_view:view.scheduler_scheduled_content.user_page';
    $block = $this->createBlockWithPrimary(
      primaryTasks: [
        $scheduleRoute => ['#link' => ['title' => 'Scheduled']],
        'entity.user.canonical' => ['#link' => ['title' => 'View']],
      ],
      currentUserId: '1',
      routeUserId: '2',
    );

    $build = $block->build();
    $this->assertArrayHasKey($scheduleRoute, $build['#primary']);
    $this->assertEquals('View', $build['#primary']['entity.user.canonical']['#link']['title']);
  }

  /**
   * Tests that no modifications are made when not on a user page.
   */
  public function testNoModificationsWhenNotOnUserPage(): void {
    $scheduleRoute = 'views_view:view.scheduler_scheduled_content.user_page';
    $block = $this->createBlockWithPrimary(
      primaryTasks: [
        $scheduleRoute => ['#link' => ['title' => 'Scheduled']],
        'entity.user.canonical' => ['#link' => ['title' => 'View']],
      ],
      currentUserId: '1',
      routeUserId: NULL,
    );

    $build = $block->build();
    $this->assertArrayHasKey($scheduleRoute, $build['#primary']);
    $this->assertEquals('View', $build['#primary']['entity.user.canonical']['#link']['title']);
  }

  /**
   * Tests that the scheduler user-page tab is removed on the user's own page.
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
   * Tests that the user canonical tab is renamed to "My pages" on own page.
   */
  public function testUserCanonicalRenamedToMyContent(): void {
    $block = $this->createBlockWithPrimary([
      'entity.user.canonical' => ['#link' => ['title' => 'View']],
      'entity.user.edit_form' => ['#link' => ['title' => 'Edit']],
    ]);

    $build = $block->build();
    $this->assertEquals(
      'My pages',
      (string) $build['#primary']['entity.user.canonical']['#link']['title'],
    );
  }

  /**
   * Creates a LocalTasksBlock with mocked dependencies.
   *
   * @param array<string, mixed> $primaryTasks
   *   Tasks to return as the primary level.
   * @param string $currentUserId
   *   The logged-in user's ID.
   * @param string|null $routeUserId
   *   The user ID from the route, or NULL if not on a user page.
   */
  private function createBlockWithPrimary(
    array $primaryTasks,
    string $currentUserId = '1',
    ?string $routeUserId = '1',
  ): LocalTasksBlock {
    $taskManager = $this->createMock(LocalTaskManagerInterface::class);
    $taskManager->method('getLocalTasks')
      ->willReturnCallback(function ($routeName, $level) use ($primaryTasks): array {
        return [
          'tabs' => $level === 0 ? $primaryTasks : [],
          'cacheability' => new CacheableMetadata(),
        ];
      });
    $this->container->set('plugin.manager.menu.local_task', $taskManager);

    $currentUser = $this->createMock(AccountProxyInterface::class);
    $currentUser->method('id')->willReturn($currentUserId);
    $currentUser->method('getPreferredAdminLangcode')->willReturn('en');
    $this->container->set('current_user', $currentUser);

    $routeUser = NULL;
    if ($routeUserId !== NULL) {
      $routeUser = $this->createMock(UserInterface::class);
      $routeUser->method('id')->willReturn($routeUserId);
    }
    $routeMatch = $this->createMock(RouteMatchInterface::class);
    $routeMatch->method('getParameters')->willReturn(new ParameterBag([]));
    $routeMatch->method('getRouteName')->willReturn('entity.user.canonical');
    $routeMatch->method('getParameter')->with('user')->willReturn($routeUser);
    $this->container->set('current_route_match', $routeMatch);

    return LocalTasksBlock::create($this->container, [], 'local_tasks_block', ['provider' => 'core']);
  }

}
