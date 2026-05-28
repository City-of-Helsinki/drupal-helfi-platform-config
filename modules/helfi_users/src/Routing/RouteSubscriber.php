<?php

namespace Drupal\helfi_users\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

class RouteSubscriber extends RouteSubscriberBase {

  protected function alterRoutes(RouteCollection $collection) {
    // Apply admin theme to user view page.
    if ($route = $collection->get('entity.user.canonical')) {
      $route->setOption('_admin_route', TRUE);
    }
  }

}
