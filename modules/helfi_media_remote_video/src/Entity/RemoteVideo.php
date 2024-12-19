<?php

declare(strict_types=1);

namespace Drupal\helfi_media_remote_video\Entity;

use Drupal\helfi_media\Entity\MediaEntityBundle;
use Drupal\media\MediaInterface;

/**
 * Bundle class for remote_video media.
 */
class RemoteVideo extends MediaEntityBundle implements MediaInterface {

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

}
