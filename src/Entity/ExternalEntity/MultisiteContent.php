<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Entity\ExternalEntity;

use Drupal\external_entities\Entity\ExternalEntity;
use Drupal\Core\Url;
use Drupal\helfi_platform_config\MultisiteContentId;

/**
 * A bundle class for Helfi: multisite content external entities.
 */
final class MultisiteContent extends ExternalEntity {

  /**
   * {@inheritdoc}
   *
   * @phpstan-return array<string, mixed>
   */
  protected function urlRouteParameters($rel) {
    $uri_route_parameters = parent::urlRouteParameters($rel);
    unset($uri_route_parameters[$this->getEntityTypeId()]);

    $segments = MultisiteContentId::toPathSegments((string) $this->id());
    if ($segments !== NULL) {
      $uri_route_parameters += $segments;
    }

    return $uri_route_parameters;
  }

  /**
   * Get the external URL of the multisite node.
   *
   * @return \Drupal\Core\Url|null
   *   The external URL, or NULL if it is missing.
   */
  public function getExternalUrl(): ?Url {
    $value = NULL;
    foreach (['entity_url', 'node_url'] as $field_name) {
      if ($this->hasField($field_name) && !$this->get($field_name)->isEmpty()) {
        $value = (string) $this->get($field_name)->value;
        break;
      }
    }
    if ($value === NULL || $value === '') {
      return NULL;
    }

    if (str_starts_with($value, '/')) {
      return Url::fromUserInput($value, ['absolute' => TRUE]);
    }

    return Url::fromUri($value, ['absolute' => TRUE]);
  }

}
