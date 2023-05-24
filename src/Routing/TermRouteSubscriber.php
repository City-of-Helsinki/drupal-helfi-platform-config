<?php

namespace Drupal\helfi_platform_config\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;
use Drupal\Core\Routing\RoutingEvents;  

/**
 * Class TermRouteSubscriber.
 *
 * A simple RouteSubscriber to alter term page routes.
 *
 * @package Drupal\helfi_platform_config\Routing
 */
class TermRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    $viewsRoute = $collection->get('view.taxonomy_term.page_1');
    $canonicalRoute = $collection->get('entity.taxonomy_term.canonical');

    if ($viewsRoute) {
      $viewsRoute->setRequirements([
        '_role' => 'authenticated',
      ]);
    }

    if ($canonicalRoute) {
      $canonicalRoute->setRequirements([
        '_role' => 'authenticated',
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // Run after the RouteSubscriber of Views, which has priority -175.
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', -180];
    return $events;
  }

}
