<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\Routing;

use Drupal\Core\Routing\RouteBuildEvent;
use Drupal\helfi_platform_config\Controller\TranslationOverviewController;
use Drupal\helfi_platform_config\Routing\TranslationOverviewRouteSubscriber;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Tests the translations overview route subscriber.
 */
#[CoversClass(TranslationOverviewRouteSubscriber::class)]
#[Group('helfi_platform_config')]
class TranslationOverviewRouteSubscriberTest extends UnitTestCase {

  /**
   * Alters a collection with the overview route using the given controller.
   *
   * @param string $controller
   *   The controller of the route.
   *
   * @return string
   *   The controller after the alter.
   */
  private function alter(string $controller): string {
    $collection = new RouteCollection();
    $collection->add('entity.node.content_translation_overview', new Route('/node/{node}/translations', [
      '_controller' => $controller,
    ]));

    (new TranslationOverviewRouteSubscriber())->onAlterRoutes(new RouteBuildEvent($collection));

    return $collection->get('entity.node.content_translation_overview')->getDefault('_controller');
  }

  /**
   * Tests that the core controller is replaced.
   */
  public function testCoreController(): void {
    $this->assertSame(
      TranslationOverviewController::class . '::overview',
      $this->alter('\Drupal\content_translation\Controller\ContentTranslationController::overview'),
    );
  }

  /**
   * Tests that a controller set by another module is kept.
   */
  public function testOtherController(): void {
    $this->assertSame('\Drupal\other\Controller\Overview::overview', $this->alter('\Drupal\other\Controller\Overview::overview'));
  }

  /**
   * Tests a collection without the route.
   */
  public function testMissingRoute(): void {
    $collection = new RouteCollection();
    (new TranslationOverviewRouteSubscriber())->onAlterRoutes(new RouteBuildEvent($collection));

    $this->assertCount(0, $collection);
  }

}
