<?php

namespace Drupal\helfi_platform_config\Token;

use Drupal\Core\Entity\EntityInterface;

/**
 * Open graph image builder.
 */
interface OGImageBuilderInterface {

  /**
   * Checks whether this builder is applicable for given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   An entity to check.
   *
   * @return bool
   *   TRUE if this instance should handle the given entity.
   */
  public function applies(?EntityInterface $entity) : bool;

  /**
   * Generate image URI.
   *
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   Entity to use for generation.
   *
   * @return string|null
   *   Image uri or NULL on failure.
   */
  public function buildUri(?EntityInterface $entity) : ?string;

}
