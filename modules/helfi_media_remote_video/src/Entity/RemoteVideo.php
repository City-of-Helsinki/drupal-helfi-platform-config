<?php

namespace Drupal\helfi_media_remote_video\Entity;

use Drupal\Core\Url;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;

/**
 * Bundle class for remote_video media.
 */
class RemoteVideo extends Media implements MediaInterface {

  /**
   * Get the video service provider url.
   *
   * @return string|null
   *   Url of video service provider.
   */
  public function getServiceUrl(): ?string {
    if (!$this->hasProvider()) {
      return NULL;
    }
    $url_resolver = \Drupal::service('media.oembed.url_resolver');
    $video_url = $this->get('field_media_oembed_video')->value;
    $provider = $url_resolver->getProviderByUrl($video_url);
    return rtrim($provider->getUrl(), '/');
  }

  /**
   * Get the video title.
   *
   * @return mixed
   *   The video title.
   */
  public function getMediaTitle() {
    return $this->get('field_media_oembed_video')
      ->iframe_title;
  }

  /**
   * Get url.
   *
   * @return Drupal\Core\Url|string
   *   The url.
   */
  public function getPrivacyPolicyUrl(): Url|string {
    return helfi_eu_cookie_compliance_get_privacy_policy_url();
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
