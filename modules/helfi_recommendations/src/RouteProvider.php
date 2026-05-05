<?php

declare(strict_types=1);

namespace Drupal\helfi_recommendations;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\helfi_recommendations\Controller\HtmxController;
use Symfony\Component\Routing\Route;

/**
 * A route provider for recommendations.
 */
final class RouteProvider implements ContainerInjectionInterface {

  use AutowireTrait;

  public function __construct(
    private readonly RecommendationManagerInterface $recommendationManager,
  ) {
  }

  /**
   * Returns an array of routes.
   *
   * @return \Symfony\Component\Routing\Route[]
   *   The route collection.
   */
  public function routes(): array {
    $routes = [];

    foreach ($this->recommendationManager->getAllowedContentTypesAndBundles() as $type => $label) {
      [$entityTypeId] = explode('|', $type);

      $routes["helfi_recommendations.{$entityTypeId}.htmx"] = new Route(
        path: "/helfi_recommendations/{$entityTypeId}/{{$entityTypeId}}/htmx",
        defaults: [
          '_controller' => HtmxController::class . '::content',
          'entity_type_id' => $entityTypeId,
        ],
        requirements: [
          '_entity_access' => "{$entityTypeId}.view",
        ],
        options: [
          '_htmx_route' => 'TRUE',
          'parameters' => [
            $entityTypeId => ['type' => 'entity:' . $entityTypeId],
          ],
        ],
      );
    }

    return $routes;
  }

}
