<?php

namespace Drupal\helfi_media_map\Entity;

use Drupal\helfi_media\Entity\MediaEntityBundle;
use Drupal\media\MediaInterface;

/**
 * Bundle class for hel_map paragraph.
 */
class HelMap extends MediaEntityBundle implements MediaInterface {

  /**
   * Get service provider url.
   *
   * @return string|null
   *   Url of the service provider.
   */
  public function getServiceUrl(): ?string {
    $map_url = $this->get('field_media_hel_map')->first()->getString();
    $url_parts = parse_url($map_url);
    return $url_parts['scheme'] . "://" . $url_parts['host'];
  }

  /**
   * Get the title of map.
   *
   * @return string|null
   *   The title of the map.
   */
  public function getMediaTitle(): ?string {
    return $this->get('field_media_hel_map')
      ->first()
      ->get('title')
      ->getValue();
  }

}
