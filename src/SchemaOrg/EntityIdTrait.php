<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\SchemaOrg;

use Drupal\Core\Entity\EntityInterface;

/**
 * Helpers for building deterministic, stable schema.org @id values.
 */
trait EntityIdTrait {

  protected const string DEFAULT_ORGANIZATION_ID = 'https://www.hel.fi/#organization';

  /**
   * Builds a @id from an entity's canonical URL and a fragment.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param string $fragment
   *   The fragment, e.g. "webpage", "breadcrumb", "jobposting".
   *
   * @return string
   *   The stable @id, e.g. "https://www.hel.fi/fi/#website".
   */
  protected function buildId(EntityInterface $entity, string $fragment): string {
    return $entity->toUrl('canonical', ['absolute' => TRUE, 'fragment' => $fragment])->toString();
  }

}
