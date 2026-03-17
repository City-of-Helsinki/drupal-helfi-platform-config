<?php

declare(strict_types=1);

namespace Drupal\helfi_media_remote_video\Entity;

use Drupal\Core\Link;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\helfi_media\Entity\MediaEntityBundle;
use Drupal\media\MediaInterface;
use Drupal\media\OEmbed\ResourceException;
use Drupal\media\OEmbed\ResourceFetcherInterface;
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
    $video_url = $this->get('field_media_oembed_video')->value ?? '';
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
   * Check if the video is hidden.
   *
   * The video can be either deleted or converted to private video in the
   * video provider.
   *
   * @return bool
   *   Returns TRUE if video is hidden, false otherwise.
   */
  public function isHidden(): bool {
    if (!$this->hasProvider()) {
      return TRUE;
    }

    $videoUrl = $this->get('field_media_oembed_video')->value ?? '';

    if (empty($videoUrl)) {
      return TRUE;
    }

    /** @var \Drupal\media\OEmbed\UrlResolverInterface $urlResolver */
    $urlResolver = \Drupal::service(UrlResolverInterface::class);

    /** @var \Drupal\media\OEmbed\ResourceFetcherInterface $resourceFetcher */
    $resourceFetcher = \Drupal::service(ResourceFetcherInterface::class);

    try {
      $resource_url = $urlResolver->getResourceUrl($videoUrl);
      $resourceFetcher->fetchResource($resource_url);
    }
    catch (ResourceException $e) {
      // The resource is hidden.
      if ($this->id() && $this->access('update')) {
        $warningText = $this->t('The video embed on this page cannot be displayed publicly.');
        $serviceProviderLink = Link::fromTextAndUrl($this->t('Please check the video visibility settings from the service provider'), Url::fromUri($videoUrl))->toString();
        $or = $this->t('or', options: ['context' => 'Remote video']);
        $editLink = Link::createFromRoute($this->t('edit the video embed.', options: ['context' => 'Remote video']), 'entity.media.edit_form', ['media' => $this->id()])->toString();
        $reminderText = $this->t('This error message is visible only to logged-in content producers.');
        $messenger = \Drupal::service(MessengerInterface::class);
        $messenger->addError(Markup::create("$warningText $serviceProviderLink $or $editLink $reminderText"));
      }
      return TRUE;
    }
    return FALSE;
  }

}
