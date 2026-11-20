<?php

declare(strict_types=1);

namespace Drupal\helfi_users\Routing;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\helfi_users\FavoriteStorage;
use Drupal\helfi_users\Hook\BulkOperationsHooks;
use Symfony\Component\Routing\RouteCollection;

/**
 * Alters routes for the helfi_users module.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * Views bulk operations routes.
   */
  private const array BULK_OPERATIONS_ROUTES = [
    'views_bulk_operations.execute_batch',
    'views_bulk_operations.update_selection',
    'views_bulk_operations.execute_configurable',
    'views_bulk_operations.confirm',
  ];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    // Apply admin theme to user view page.
    if ($route = $collection->get('entity.user.canonical')) {
      $route->setOption('_admin_route', TRUE);
    }

    // Allow only installed favorite entity types.
    if ($route = $collection->get('helfi_users.favorite_toggle')) {
      $entityTypes = [];
      foreach (FavoriteStorage::ENTITY_TYPES as $entityType) {
        if ($this->entityTypeManager->hasDefinition($entityType)) {
          $entityTypes[] = $entityType;
        }
      }
      if ($entityTypes) {
        $route->setRequirement('entity_type', implode('|', $entityTypes));
      }
      else {
        $collection->remove('helfi_users.favorite_toggle');
      }
    }

    // Require a permission to use views bulk operations.
    foreach (self::BULK_OPERATIONS_ROUTES as $routeName) {
      $collection->get($routeName)?->setRequirement('_permission', BulkOperationsHooks::PERMISSION);
    }
  }

}
