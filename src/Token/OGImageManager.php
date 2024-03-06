<?php

namespace Drupal\helfi_platform_config\Token;

use Drupal\Core\Entity\EntityInterface;

/**
 * Open graph image manager.
 *
 * Modules using this service should still implement
 * hook_token_info() for [your-type:shareable-image] token.
 *
 * @see helfi_platform_config_tokens()
 */
final class OGImageManager {

  /**
   * Builders.
   *
   * @var array
   */
  private array $builders = [];

  /**
   * Sorted builders.
   *
   * @var array
   */
  private array $sortedBuilders;

  /**
   * Adds image builder.
   *
   * @param \Drupal\helfi_platform_config\Token\OGImageBuilderInterface $builder
   *   Builder to add.
   * @param int $priority
   *   Builder priority.
   */
  public function add(OGImageBuilderInterface $builder, int $priority = 0) : void {
    $this->builders[$priority][] = $builder;
    $this->sortedBuilders = [];
  }

  /**
   * Builds image url for given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return string|null
   *   OG image url or NULL on failure.
   */
  public function buildUrl(EntityInterface $entity) : ?string {
    $image_url = NULL;

    foreach ($this->getBuilders() as $builder) {
      if ($builder->applies($entity)) {
        if ($url = $builder->buildUrl($entity)) {
          // Replace the value only if buildUrl return non-NULL values.
          // This allows previous image builders to provide default images
          // in case field value is missing etc.
          $image_url = $url;
        }
      }
    }

    return $image_url;
  }

  /**
   * Gets sorted list of image builders.
   *
   * @return \Drupal\helfi_platform_config\Token\OGImageBuilderInterface[]
   *   Image builders sorted according to priority.
   */
  private function getBuilders() : array {
    if (empty($this->sortedBuilders)) {
      krsort($this->builders);
      $this->sortedBuilders = array_merge(...$this->builders);
    }

    return $this->sortedBuilders;
  }

}
