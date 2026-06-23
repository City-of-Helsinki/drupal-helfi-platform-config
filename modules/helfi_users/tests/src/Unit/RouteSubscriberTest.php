<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_users\Unit;

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
   * Tests that _admin_route is set on the user canonical route when present.
   */
  public function testAdminThemeAppliedToUserCanonical(): void {
    $route = new Route('/user/{user}');
    $collection = new RouteCollection();
    $collection->add('entity.user.canonical', $route);

    (new RouteSubscriber())->onAlterRoutes(new RouteBuildEvent($collection));

    $this->assertTrue($route->getOption('_admin_route'));
  }

  /**
   * Tests that nothing breaks when the user canonical route is absent.
   */
  public function testNoErrorWhenUserCanonicalRouteAbsent(): void {
    $collection = new RouteCollection();

    (new RouteSubscriber())->onAlterRoutes(new RouteBuildEvent($collection));

    $this->assertNull($collection->get('entity.user.canonical'));
  }

}
