<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Routing;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\EnhancerInterface;
use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\helfi_platform_config\MultisiteContentId;
use Symfony\Component\HttpFoundation\Request;

/**
 * Exposes the split-path entity under its entity type parameter name.
 *
 * Canonical routes use {instance}/{datasource}/{item}. The param converter
 * upcasts {instance} to the entity; access and _entity_view still look for
 * {helfi_multisite_content}.
 */
final class MultisiteContentRouteEnhancer implements EnhancerInterface {

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, mixed> $defaults
   * @phpstan-return array<string, mixed>
   */
  public function enhance(array $defaults, Request $request): array {
    $route_name = $defaults[RouteObjectInterface::ROUTE_NAME] ?? '';
    if (!is_string($route_name) || !str_starts_with($route_name, 'entity.' . MultisiteContentId::ENTITY_TYPE_ID . '.')) {
      return $defaults;
    }

    $instance = $defaults['instance'] ?? NULL;
    if ($instance instanceof EntityInterface) {
      $defaults[MultisiteContentId::ENTITY_TYPE_ID] = $instance;
    }

    return $defaults;
  }

}
