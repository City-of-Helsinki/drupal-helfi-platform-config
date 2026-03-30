<?php

declare(strict_types=1);

namespace Drupal\helfi_media_remote_video\Hook;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\media\IFrameMarkup;
use Drupal\media\MediaInterface;
use Drupal\paragraphs\ParagraphInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Preprocess hooks for helfi_media_remote_video module.
 */
class PreprocessHooks {

  use AutowireTrait;

  public function __construct(
    private readonly RequestStack $requestStack,
  ) {}

  /**
   * Implements hook_preprocess_HOOK().
   */
  #[Hook('preprocess_media_oembed_iframe')]
  public function preprocessMediaOembedIframe(array &$variables): void {
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

    // For Icareus HUS (Terveyskylä) videos, ensure the subtitles parameter
    // from the source URL is preserved in the embed iframe. The oEmbed API
    // does not include it in the returned HTML.
    if (str_contains($iframe, 'players.icareus.com/hus/')) {
      if ($source_url = $this->requestStack->getCurrentRequest()->query->get('url')) {
        if ($query = parse_url($source_url, PHP_URL_QUERY)) {
          parse_str($query, $params);
          if (!empty($params['subtitles'])) {
            // Encode to prevent injection of HTML.
            $subtitles = htmlentities(urlencode($params['subtitles']));

            // Inject the subtitles query parameter into the iframe URL.
            $iframe = preg_replace_callback(
              '/(src=["\'])([^"\']*players\.icareus\.com\/hus\/embed\/vod\/[^"\']*)/',
              static fn(array $m): string => $m[1] . $m[2] . (str_contains($m[2], '?') ? '&' : '?') . 'subtitles=' . $subtitles,
              $iframe,
            );
          }
        }
      }
    }

    $variables['media'] = IFrameMarkup::create($iframe);
  }

}
