<?php

namespace Drupal\helfi_platform_config\Token;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;

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
   * Constructs a new instance.
   */
  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
  ) {
  }

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
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   The entity.
   *
   * @return string|null
   *   OG image url or NULL on failure.
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

    if (!$image_uri) {
      return NULL;
    }

    // Let modules alter the final uri (like apply image styles).
    $this->moduleHandler->alter('og_image_uri', $image_uri);

    if (UrlHelper::isExternal($image_uri)) {
      return $image_uri;
    }

    return $this->fileUrlGenerator->generateAbsoluteString($image_uri);
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
