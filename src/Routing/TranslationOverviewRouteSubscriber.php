<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Routing;

use Drupal\content_translation\Controller\ContentTranslationController;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Drupal\helfi_platform_config\Controller\TranslationOverviewController;
use Symfony\Component\Routing\RouteCollection;

/**
 * Replaces the controller of the node translations overview.
 */
class TranslationOverviewRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    $route = $collection->get('entity.node.content_translation_overview');

    // Keep a controller that another module has set.
    if (ltrim((string) $route?->getDefault('_controller'), '\\') !== ContentTranslationController::class . '::overview') {
      return;
    }

    $route->setDefault('_controller', TranslationOverviewController::class . '::overview');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Run after ContentTranslationRouteSubscriber, which adds the route with
    // priority -210.
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', -220];
    return $events;
  }

}
