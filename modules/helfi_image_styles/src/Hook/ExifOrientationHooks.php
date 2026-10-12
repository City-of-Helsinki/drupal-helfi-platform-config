<?php

declare(strict_types=1);

namespace Drupal\helfi_image_styles\Hook;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\file\FileInterface;
use Drupal\helfi_image_styles\ExifOrientation;

/**
 * Rotates the added images according to their EXIF orientation.
 *
 * @see \Drupal\helfi_image_styles\ExifOrientation
 */
final class ExifOrientationHooks {

  use AutowireTrait;

  public function __construct(
    private readonly ExifOrientation $exifOrientation,
  ) {
  }

  /**
   * Implements hook_ENTITY_TYPE_presave() for file entities.
   */
  #[Hook('file_presave')]
  public function filePresave(FileInterface $file): void {
    // The image is corrected when it's added. Checking the existing files
    // would download them from the blob storage on every save. Use
    // 'drush helfi:image-styles:fix-exif-orientation' to fix the existing
    // images.
    if (!$file->isNew()) {
      return;
    }
    $this->exifOrientation->correct($file);
  }

}
