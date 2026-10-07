<?php

declare(strict_types=1);

namespace Drupal\helfi_image_styles;

use Drupal\Core\Image\ImageFactory;
use Drupal\file\FileInterface;
use Drupal\imagemagick\ArgumentMode;
use Drupal\imagemagick\ImagemagickExecArguments;
use Drupal\imagemagick\PackageCommand;
use Drupal\imagemagick\Plugin\ImageToolkit\ImagemagickToolkit;

/**
 * Rotates the images according to their EXIF orientation.
 *
 * Phones store the images in the sensor's orientation and tell the viewers to
 * rotate them with the EXIF orientation tag. Browsers don't apply the tag to
 * WebP images, so the WebP derivatives would be shown sideways or upside down.
 *
 * Based on the EXIF Orientation module, which doesn't work with ImageMagick.
 *
 * @see https://www.drupal.org/project/exif_orientation
 */
final readonly class ExifOrientation {

  /**
   * The MIME types that can have the EXIF orientation.
   */
  public const array MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

  public function __construct(
    private ImageFactory $imageFactory,
  ) {
  }

  /**
   * Gets the EXIF orientation that needs to be corrected.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file.
   *
   * @return int|null
   *   The EXIF orientation (2-8), or NULL if the image doesn't need rotating.
   */
  public function getOrientation(FileInterface $file): ?int {
    return $this->load($file)[1] ?? NULL;
  }

  /**
   * Rotates the image according to its EXIF orientation.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file. Its size is updated, but it isn't saved.
   *
   * @return int|null
   *   The corrected EXIF orientation (2-8), or NULL if the image wasn't
   *   rotated.
   */
  public function correct(FileInterface $file): ?int {
    if (!$loaded = $this->load($file)) {
      return NULL;
    }
    [$image, $orientation] = $loaded;
    /** @var \Drupal\imagemagick\Plugin\ImageToolkit\ImagemagickToolkit $toolkit */
    $toolkit = $image->getToolkit();

    // Rotates and mirrors the image and resets the orientation tag.
    $toolkit->arguments()->add(['-auto-orient']);

    // Keep the original quality instead of the configured one, which is meant
    // for the derivatives.
    if ($quality = $this->getQuality($toolkit)) {
      $toolkit->arguments()->add(['-quality', (string) $quality]);
    }

    // Orientations 5-8 rotate the image by 90 or 270 degrees.
    if ($this->swapsDimensions($orientation)) {
      $width = $toolkit->getWidth();
      $toolkit->setWidth($toolkit->getHeight())->setHeight($width);
    }

    if (!$image->save()) {
      return NULL;
    }
    // The file entity recorded its size before the image was rewritten.
    $file->setSize($image->getFileSize());

    return $orientation;
  }

  /**
   * Checks whether the orientation swaps the width and the height.
   *
   * @param int $orientation
   *   The EXIF orientation.
   *
   * @return bool
   *   TRUE if the image is rotated by 90 or 270 degrees.
   */
  public function swapsDimensions(int $orientation): bool {
    return $orientation >= 5;
  }

  /**
   * Loads the image that needs rotating.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file.
   *
   * @return array{\Drupal\Core\Image\ImageInterface, int}|null
   *   The image and its EXIF orientation, or NULL if the image doesn't need
   *   rotating.
   */
  private function load(FileInterface $file): ?array {
    if (!in_array($file->getMimeType(), self::MIME_TYPES, TRUE)) {
      return NULL;
    }
    $image = $this->imageFactory->get($file->getFileUri());
    $toolkit = $image->getToolkit();

    if (!$image->isValid() || !$toolkit instanceof ImagemagickToolkit) {
      return NULL;
    }
    $orientation = $toolkit->getExifOrientation();

    // 1 is the normal orientation. Values 2-8 are mirrored and/or rotated.
    if ($orientation === NULL || $orientation < 2 || $orientation > 8) {
      return NULL;
    }
    return [$image, $orientation];
  }

  /**
   * Gets the quality of the source image.
   *
   * @param \Drupal\imagemagick\Plugin\ImageToolkit\ImagemagickToolkit $toolkit
   *   The toolkit.
   *
   * @return int|null
   *   The quality estimated by ImageMagick, or NULL if it's not known.
   */
  private function getQuality(ImagemagickToolkit $toolkit): ?int {
    $arguments = new ImagemagickExecArguments($toolkit->getExecManager());
    $arguments->setSourceLocalPath($toolkit->ensureSourceLocalPath());
    $arguments->add(['-format', '%Q'], ArgumentMode::PreSource);

    $output = '';
    $error = '';
    if (!$toolkit->getExecManager()->execute(PackageCommand::Identify, $arguments, $output, $error)) {
      return NULL;
    }
    $quality = (int) trim($output);

    return $quality > 0 && $quality <= 100 ? $quality : NULL;
  }

}
