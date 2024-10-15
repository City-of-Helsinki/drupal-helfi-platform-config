<?php

namespace Drupal\helfi_media_map\Entity;

use Drupal\Core\Url;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;

/**
 * Bundle class for hel_map paragraph.
 */
class HelMap extends Media implements MediaInterface {

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

  /**
   * Get url.
   *
   * @return \Drupal\Core\Url|string
   *   The url.
   */
  public function getPrivacyPolicyUrl(): Url|string {
    $url = helfi_eu_cookie_compliance_get_privacy_policy_url();
    assert($url instanceof Url);
    return $url;
  }

  /**
   * Get js library path.
   *
   * @return string
   *   The js library path.
   */
  public function getJsLibrary(): string {
    return 'hdbt/embedded-content-cookie-compliance';
  }

  /**
   * Check if module exists.
   *
   * @return bool
   *   Module exists.
   */
  protected function hasProvider(): bool {
    return \Drupal::moduleHandler()->moduleExists('oembed_providers') &&
      $this->hasField('field_media_oembed_video');
  }

}
