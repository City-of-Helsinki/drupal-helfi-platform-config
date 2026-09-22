<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\helfi_platform_config\MultisiteContentId;
use Symfony\Component\Routing\RouteCollection;

/**
 * Uses a split path for helfi_multisite_content canonical routes.
 */
final class MultisiteContentRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    $routes = [
      'entity.' . MultisiteContentId::ENTITY_TYPE_ID . '.canonical' => '',
      'entity.' . MultisiteContentId::ENTITY_TYPE_ID . '.edit_form' => '/edit',
      'entity.' . MultisiteContentId::ENTITY_TYPE_ID . '.delete_form' => '/delete',
    ];

    foreach ($routes as $name => $suffix) {
      $route = $collection->get($name);
      if (!$route) {
        continue;
      }

      $route->setPath(MultisiteContentId::canonicalPathTemplate($suffix));
      $parameters = $route->getOption('parameters') ?? [];
      unset($parameters[MultisiteContentId::ENTITY_TYPE_ID]);
      $parameters['instance'] = [
        'type' => 'entity:' . MultisiteContentId::ENTITY_TYPE_ID,
        'converter' => 'Drupal\helfi_platform_config\ParamConverter\MultisiteContentParamConverter',
      ];
      $route->setOption('parameters', $parameters);

      $access = $route->getRequirement('_entity_access');
      if (is_string($access) && str_starts_with($access, MultisiteContentId::ENTITY_TYPE_ID . '.')) {
        $route->setRequirement('_entity_access', 'instance' . substr($access, strlen(MultisiteContentId::ENTITY_TYPE_ID)));
      }
    }

    $collection_route = $collection->get('entity.' . MultisiteContentId::ENTITY_TYPE_ID . '.collection');
    if ($collection_route) {
      $collection_route->setPath('/' . MultisiteContentId::PATH_PREFIX);
    }
  }

}
