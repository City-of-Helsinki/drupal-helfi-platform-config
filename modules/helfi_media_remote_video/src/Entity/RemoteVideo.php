<?php

declare(strict_types=1);

namespace Drupal\helfi_media_remote_video\Entity;

use Drupal\helfi_media\Entity\MediaEntityBundle;
use Drupal\media\MediaInterface;
use Drupal\media\OEmbed\UrlResolverInterface;

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
    /** @var \Drupal\media\OEmbed\UrlResolverInterface $url_resolver */
    $url_resolver = \Drupal::service(UrlResolverInterface::class);
    $video_url = $this->get('field_media_oembed_video')->value;
    try {
      $provider = $url_resolver->getProviderByUrl($video_url);
      return rtrim($provider->getUrl(), '/');
    }
    // UrlResolverInterface::getProviderByUrl makes
    // network requests that can fail.
    catch (\Exception) {
      return NULL;
    }
  }

  /**
   * Get the video title.
   *
   * @return mixed
   *   The video title.
   */
  public function getMediaTitle(): mixed {
    return $this->get('field_media_oembed_video')
      ->iframe_title;
  }

}
