<?php

namespace Drupal\helfi_tpr_config\Entity;

use Drupal\helfi_tpr\Entity\Unit as BaseUnit;
use Drupal\image\Entity\ImageStyle;

/**
 * Bundle class for tpr unit.
 */
class Unit extends BaseUnit {

  /**
   * Gets the picture url with image style.
   *
   * @param \Drupal\image\Entity\ImageStyle|null $imageStyle
   *   URL image style. Original image is returned if style is NULL.
   *
   * @return string|null
   *   The picture url.
   */
  public function getPictureUrlWithImageStyle(ImageStyle $imageStyle = NULL) : ? string {
    /** @var \Drupal\media\MediaInterface $picture_url */
    $picture_url = $this->get('picture_url_override')->entity;

    if (!$picture_url) {
      $url = $this->get('picture_url')->value;

      // Run image url through imagecache_external so that we
      // can apply image style.
      if ($imageStyle) {
        $image_path = imagecache_external_generate_path($url);

        if ($image_path) {
          return $imageStyle->buildUrl($image_path);
        }

        return NULL;
      }

      return $url;
    }

    if ($file = $picture_url->get('field_media_image')->entity) {
      /** @var \Drupal\file\FileInterface $file */
      if ($imageStyle) {
        return $imageStyle->buildUrl($file->getFileUri());
      }

      try {
        return $file->createFileUrl(FALSE) ?: NULL;
      }
      catch (\Exception) {
      }
    }
    return NULL;
  }

}
