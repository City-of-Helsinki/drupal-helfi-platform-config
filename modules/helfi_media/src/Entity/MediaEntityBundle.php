<?php

declare(strict_types=1);

namespace Drupal\helfi_media\Entity;

use Drupal\Core\Url;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;

/**
 * Bundle class for hel_map paragraph.
 */
class MediaEntityBundle extends Media implements MediaInterface {

  /**
   * Get url.
   *
   * @return \Drupal\Core\Url|null
   *   The url.
   */
  public function getPrivacyPolicyUrl(): Url|NULL {
    $url = NULL;

    if (\Drupal::moduleHandler()->moduleExists('hdbt_cookie_banner')) {
      /** @var \Drupal\hdbt_cookie_banner\Services\CookieSettings $cookie_settings */
      $cookie_settings = \Drupal::service('hdbt_cookie_banner.cookie_settings');
      $url = $cookie_settings->getCookieSettingsPageUrl();
      assert($url instanceof Url);
    }
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

  /**
   * Temporary iframe title value for embed medias.
   *
   * @var string|null
   */
  public ?string $iframeTitle;

}
