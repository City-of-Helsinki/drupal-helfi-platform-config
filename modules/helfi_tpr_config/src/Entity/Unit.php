<?php

declare(strict_types=1);

namespace Drupal\helfi_tpr_config\Entity;

use Drupal\helfi_tpr\Entity\Unit as BaseUnit;

/**
 * Bundle class for tpr unit.
 */
class Unit extends BaseUnit {

  /**
   * Gets the picture uri.
   *
   * @return string|null
   *   The picture url.
   */
  public function getPictureUri() : ? string {
    /** @var \Drupal\media\MediaInterface $picture_url */
    $picture_url = $this->get('picture_url_override')->entity;

    if (!$picture_url) {
      $url = $this->get('picture_url')->value;

      // Run url through imagecache_external so that it is possible
      // to apply image styles later. This method is in a bundle class
      // so that helfi_tpr does not have to add dependency to
      // imagecache_external.
      if ($url) {
        return imagecache_external_generate_path($url) ?: NULL;
      }

      return NULL;
    }

    if ($file = $picture_url->get('field_media_image')->entity) {
      /** @var \Drupal\file\FileInterface $file */
      return $file->getFileUri();
    }

    return NULL;
  }

}
