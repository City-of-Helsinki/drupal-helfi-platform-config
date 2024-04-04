<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * A simple RouteSubscriber to alter user.admin_create route.
 */
class UserCreateRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // Let only super administrators create users.
    if ($route = $collection->get('user.admin_create')) {
      $route->addRequirements([
        '_role' => 'super_administrator',
      ]);
    }
  }

}
