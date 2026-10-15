<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Image;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;

/**
 * Builds an absolute liftup image URL for an entity.
 */
final class LiftupImageManager extends ImageManagerBase {

  /**
   * Constructs a new instance.
   */
  public function __construct(
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
  ) {
  }

  /**
   * Builds an absolute image URL for the given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   The entity.
   *
   * @return string|null
   *   Absolute image URL or NULL when no builder produced a URI.
   */
  public function buildUrl(?EntityInterface $entity) : ?string {
    $image_uri = parent::buildUrl($entity);

    if (!$image_uri) {
      return NULL;
    }

    if (UrlHelper::isExternal($image_uri)) {
      return $image_uri;
    }

    return $this->fileUrlGenerator->generateAbsoluteString($image_uri);
  }

}
