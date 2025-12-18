<?php

declare(strict_types=1);

namespace Drupal\helfi_media_remote_video\Hook;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\media\IFrameMarkup;

/**
 * Hooks for helfi_media_remote_video module.
 */
class RemoteVideoHooks {

  use AutowireTrait;

  /**
   * Implements hook_preprocess_HOOK().
   */
  #[Hook('preprocess_media_oembed_iframe')]
  public static function preprocessMediaOembedIframe(array &$variables): void {
    $iframe = $variables['media']->__toString();

    // Add scrolling="no" attribute to the inner iframe.
    $iframe = str_replace(
      '></iframe>',
      ' scrolling="no"></iframe>',
      $iframe,
    );

    // Replace the iframe URL with a no-cookie version and rebuild the markup.
    // This cannot be done via the media entity itself as it only affects
    // the URL which is sent to YouTube Oembed API.
    // See: https://www.drupal.org/i/3043821.
    if (str_contains($iframe, 'youtube.com')) {
      $iframe = str_replace(
        'youtube.com/',
        'youtube-nocookie.com/',
        $iframe,
      );
    }

    $variables['media'] = IFrameMarkup::create($iframe);
  }

}
