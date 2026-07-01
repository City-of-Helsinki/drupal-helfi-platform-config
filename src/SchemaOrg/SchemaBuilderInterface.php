<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\SchemaOrg;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Builds schema.org tags for entities.
 *
 * @see \Drupal\helfi_platform_config\SchemaOrg\SchemaManager
 */
interface SchemaBuilderInterface {

  /**
   * Checks whether this builder is applicable for the given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   The main entity of the current page, or NULL when the page has none
   *   (e.g. a views listing).
   */
  public function applies(?EntityInterface $entity): bool;

  /**
   * Builds the schema.org entities to merge into the page graph.
   *
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   The main entity of the current page, or NULL.
   * @param \Drupal\Core\Cache\RefinableCacheableDependencyInterface $cacheability
   *   Cacheability for the page. Builders must register cache dependencies
   *   their output relies on so the JSON-LD is invalidated correctly.
   *
   * @return array<string, mixed>[]
   *   A list of schema.org entity arrays. Return an empty array
   *   to contribute nothing.
   */
  public function build(?EntityInterface $entity, RefinableCacheableDependencyInterface $cacheability): array;

}
