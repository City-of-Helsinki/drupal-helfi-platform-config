<?php

declare(strict_types=1);

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
   * @return string
   *   Url of the service provider.
   */
  public function getServiceUrl(): string {
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
    $title = (string) $this->get('field_media_hel_map')
      ->first()
      ->get('title')
      ->getValue();

    return empty($title) ? NULL : $title;
  }

   /**
   * Check if provider is palvelukartta.
   *
   * @return bool
   *   TRUE if provider is palvelukartta, FALSE otherwise.
   */
  public function getCookieConsentBypass(): bool {
    $link = $this->get('field_media_hel_map')->uri;
    return $link ? str_contains($link, 'palvelukartta.hel.fi') : false;
  }
}
