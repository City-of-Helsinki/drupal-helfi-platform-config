<?php

declare(strict_types=1);

namespace Drupal\helfi_users\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Alters routes for the helfi_users module.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    // Apply admin theme to user view page.
    if ($route = $collection->get('entity.user.canonical')) {
      $route->setOption('_admin_route', TRUE);
    }
  }

}
