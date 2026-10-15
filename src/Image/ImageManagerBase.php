<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Image;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\helfi_platform_config\Image\ImageBuilderInterface;

/**
 * Image manager base.
 *
 */
abstract class ImageManagerBase {

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
   * @param \Drupal\helfi_platform_config\Image\ImageBuilderInterface $builder
   *   Builder to add.
   * @param int $priority
   *   Builder priority.
   */
  public function add(ImageBuilderInterface $builder, int $priority = 0) : void {
    $this->builders[$priority][] = $builder;
    $this->sortedBuilders = [];
  }

  /**
   * Builds image url for given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   The entity.
   *
   * @return string|null
   *   Image url or NULL on failure.
   */
  public function buildUrl(?EntityInterface $entity) : ?string {
    $image_uri = NULL;

    foreach ($this->getBuilders() as $builder) {
      if ($builder->applies($entity)) {
        // Replace the return value only if buildUri return non-NULL values.
        // This allows previous image builders to provide default images
        // in case field value is missing etc.
        if ($uri = $builder->buildUri($entity)) {
          $image_uri = $uri;
        }
      }
    }

    return $image_uri ?? NULL;
  }

  /**
   * Gets sorted list of image builders.
   *
   * @return \Drupal\helfi_platform_config\Image\ImageBuilderInterface[]
   *   Image builders sorted according to priority.
   */
  private function getBuilders() : array {
    if (empty($this->sortedBuilders)) {
      ksort($this->builders);
      $this->sortedBuilders = array_merge(...$this->builders);
    }

    return $this->sortedBuilders;
  }

}
