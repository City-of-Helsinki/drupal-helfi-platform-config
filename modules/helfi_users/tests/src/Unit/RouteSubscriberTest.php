<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_users\Unit;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteBuildEvent;
use Drupal\helfi_users\Routing\RouteSubscriber;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Tests RouteSubscriber.
 */
#[CoversClass(RouteSubscriber::class)]
#[Group('helfi_users')]
class RouteSubscriberTest extends UnitTestCase {

  /**
   * Creates the route subscriber with the given installed entity types.
   *
   * @param string[] $installed
   *   The installed entity type IDs.
   *
   * @return \Drupal\helfi_users\Routing\RouteSubscriber
   *   The route subscriber.
   */
  private function createSubscriber(array $installed = ['node']): RouteSubscriber {
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('hasDefinition')
      ->willReturnCallback(fn (string $id): bool => in_array($id, $installed, TRUE));
    return new RouteSubscriber($entityTypeManager);
  }

  /**
   * Tests that _admin_route is set on the user canonical route when present.
   */
  public function testAdminThemeAppliedToUserCanonical(): void {
    $route = new Route('/user/{user}');
    $collection = new RouteCollection();
    $collection->add('entity.user.canonical', $route);

    $this->createSubscriber()->onAlterRoutes(new RouteBuildEvent($collection));

    $this->assertTrue($route->getOption('_admin_route'));
  }

  /**
   * Tests that nothing breaks when the user canonical route is absent.
   */
  public function testNoErrorWhenUserCanonicalRouteAbsent(): void {
    $collection = new RouteCollection();

    $this->createSubscriber()->onAlterRoutes(new RouteBuildEvent($collection));

    $this->assertNull($collection->get('entity.user.canonical'));
  }

  /**
   * Tests that the favorite route allows only installed entity types.
   */
  public function testFavoriteRouteAllowsOnlyInstalledEntityTypes(): void {
    $route = new Route('/admin/favorite/{entity_type}/{entity}/{display}');
    $collection = new RouteCollection();
    $collection->add('helfi_users.favorite_toggle', $route);

    $this->createSubscriber(['node', 'tpr_unit'])->onAlterRoutes(new RouteBuildEvent($collection));

    $this->assertEquals('node|tpr_unit', $route->getRequirement('entity_type'));
  }

}
