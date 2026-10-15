<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Token;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\helfi_platform_config\Image\ImageManagerBase;

/**
 * Open graph image manager.
 *
 * Modules using this service should still implement
 * hook_token_info() for [your-type:shareable-image] token.
 *
 * @see helfi_platform_config_tokens()
 */
final class OGImageManager extends ImageManagerBase {

  /**
   * Constructs a new instance.
   */
  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
  ) {
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
    $image_uri = parent::buildUrl($entity);

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

}
