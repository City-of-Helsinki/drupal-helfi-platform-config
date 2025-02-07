<?php

declare(strict_types=1);

namespace Drupal\helfi_media_remote_video\Services;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\media\OEmbed\ProviderException;
use Drupal\media\OEmbed\UrlResolverInterface;
use Exception;

/**
 * Defines UrlResolver service class.
 */
class UrlResolver {

  use StringTranslationTrait;

  /**
   * Define the video URL pattern for Helsinki Kanava.
   *
   * @code
   *   An example of the URL:
   *   https://suite.icareus.com/api/oembed?url=https://www.helsinkikanava.fi/fi/web/helsinkikanava/player/vod?assetId=141721719&maxwidth=1264&maxheight=714
   * @endcode
   */
  private const VIDEO_URL_PATTERN = 'https://players.icareus.com/helsinkikanava/embed/vod/*';

  /**
   * Define the URL patterns to match the video URL.
   */
  public const PATTERNS = [
    'event_details' => [
      'example' => 'https://www.helsinkikanava.fi/*/event/details/*',
      'url_match' => 'event/details',
      'regex' => '/\/(\d+)$/',
    ],
    'video_details' => [
      'example' => 'https://www.helsinkikanava.fi/*/video/details/*',
      'url_match' => 'video/details',
      'regex' => '/\/(\d+)$/',
    ],
    'player_event_view' => [
      'example' => 'https://www.helsinkikanava.fi/*/web/helsinkikanava/player/event/view?*assetId=*',
      'url_match' => 'player/event/view',
      'regex' => '/assetId=(\d+)/',
    ],
    'webcast' => [
      'example' => 'https://www.helsinkikanava.fi/*/webcast?*assetId=*',
      'url_match' => '/web/helsinkikanava/player/webcast',
      'regex' => '/assetId=(\d+)/',
    ],
  ];

  public function __construct(
    private readonly UrlResolverInterface $urlResolver,
  ) {
  }

  /**
   * Convert video URL to suitable format for the Icareus Suite oembed.
   *
   * @param string $url
   *   URL to be converted.
   *
   * @return string|null
   *   Video URL as a string or null.
   *
   * @throws \Exception
   *   Throws exception if URL is missing asset ID parameter or no valid
   *   pattern matched the URL.
   */
  public function convertUrl(string $url): ?string {
    try {
      $provider = $this->urlResolver->getProviderByUrl($url);
    }
    catch (ProviderException $providerException) {
      return NULL;
    }
    // Handle only Helsinki-kanava videos (Icareus Suite).
    if ($provider->getName() !== 'Icareus Suite') {
      return NULL;
    }

    $converted_url = NULL;

    foreach (self::PATTERNS as $pattern) {
      // Check if the URL matches the description and extract the asset ID.
      if (
        str_contains($url, $pattern['url_match']) &&
        preg_match($pattern['regex'], $url, $matches)
      ) {
        $asset_id = $matches[1] ?? NULL;

        if (empty($asset_id)) {
          throw new Exception('URL is missing asset ID parameter.');
        }

        // Replace the placeholder '*' with the asset ID.
        $converted_url = str_replace('*', $asset_id, self::VIDEO_URL_PATTERN);

        // Exit the loop once a match is found.
        break;
      }
    }

    // Return the converted URL or throw an exception if no match was found.
    return $converted_url ?? throw new Exception('No valid pattern matched the URL.');
  }

}
