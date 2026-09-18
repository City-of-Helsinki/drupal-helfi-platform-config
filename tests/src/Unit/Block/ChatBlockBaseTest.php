<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\helfi_platform_config\Plugin\Block\IbmChatApp;
use Drupal\helfi_platform_config\Plugin\Block\TeliaAceAuthenticatedWidget;
use Drupal\helfi_platform_config\Plugin\Block\TeliaAceWidget;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tests chat block access on error pages.
 */
#[Group('helfi_platform_config')]
class ChatBlockBaseTest extends UnitTestCase {

  /**
   * Chat blocks sharing the error page access check.
   */
  private const array CHAT_BLOCKS = [
    'ibm_chat_app' => IbmChatApp::class,
    'telia_ace_widget' => TeliaAceWidget::class,
    'telia_ace_authenticated_widget' => TeliaAceAuthenticatedWidget::class,
  ];

  /**
   * Tests block access per route.
   */
  #[DataProvider('routeData')]
  public function testBlockAccess(string $routeName, bool $expected): void {
    $routeMatch = $this->createMock(RouteMatchInterface::class);
    $routeMatch->method('getRouteName')->willReturn($routeName);

    foreach (self::CHAT_BLOCKS as $pluginId => $class) {
      $block = new $class([], $pluginId, ['provider' => 'helfi_platform_config'], $routeMatch);
      $access = $block->access($this->createMock(AccountInterface::class), TRUE);

      $this->assertInstanceOf(AccessResult::class, $access);
      $this->assertSame($expected, $access->isAllowed(), sprintf('%s is visible on %s', $pluginId, $routeName));
      $this->assertContains('route.name', $access->getCacheContexts());
    }
  }

  /**
   * Tests that create() autowires the route match.
   */
  public function testCreate(): void {
    $container = $this->createMock(ContainerInterface::class);
    $container->method('has')->with(RouteMatchInterface::class)->willReturn(TRUE);
    $container->method('get')
      ->with(RouteMatchInterface::class)
      ->willReturn($this->createMock(RouteMatchInterface::class));

    foreach (self::CHAT_BLOCKS as $pluginId => $class) {
      $block = $class::create($container, [], $pluginId, ['provider' => 'helfi_platform_config']);

      $this->assertInstanceOf($class, $block);
    }
  }

  /**
   * Data provider for `testBlockAccess`.
   *
   * @phpstan-return array<string, array{string, bool}>
   */
  public static function routeData(): array {
    return [
      'node page' => ['entity.node.canonical', TRUE],
      'unauthorized' => ['system.401', FALSE],
      'forbidden' => ['system.403', FALSE],
      'not found' => ['system.404', FALSE],
      'generic client error' => ['system.4xx', FALSE],
    ];
  }

}
