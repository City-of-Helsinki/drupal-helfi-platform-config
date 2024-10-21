<?php

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
   * @return \Drupal\Core\Url|NULL
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
    // @todo UHF-10862 Remove once the HDBT cookie banner module is in use.
    elseif (\Drupal::moduleHandler()->moduleExists('helfi_eu_cookie_compliance')) {
      $url = helfi_eu_cookie_compliance_get_privacy_policy_url();
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
}
