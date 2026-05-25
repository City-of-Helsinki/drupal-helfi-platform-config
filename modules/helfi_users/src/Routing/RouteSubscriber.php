<?php

namespace Drupal\helfi_users\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

class RouteSubscriber extends RouteSubscriberBase {

  protected function alterRoutes(RouteCollection $collection) {
    // Apply admin theme to user canonical page.
    if ($route = $collection->get('entity.user.canonical')) {
      $route->setOption('_admin_route', TRUE);
    }
    // Optionally also for the edit form:
    if ($route = $collection->get('entity.user.edit_form')) {
      $route->setOption('_admin_route', TRUE);
    }
  }

}
