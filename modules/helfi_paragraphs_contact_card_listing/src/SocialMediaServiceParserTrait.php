<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_contact_card_listing;

/**
 * Social media parser helper.
 */
trait SocialMediaServiceParserTrait {

  /**
   * Process the social Media link.
   *
   * @param string|null $social_media_link_uri
   *   Social Media uri as a string.
   *
   * @return array
   *   Social Media service as an array.
   */
  protected function processSocialMediaDomain(?string $social_media_link_uri): array {
    if (empty($social_media_link_uri)) {
      throw new \LogicException('Invalid url.');
    }

    // Parse the URL to get the host.
    $parsed_social_media_url = parse_url($social_media_link_uri);
    $host = $parsed_social_media_url['host'] ?? '';

    // Split the host into parts.
    $host_parts = explode('.', $host);

    // Extract the main domain. Assuming it's the second-to-last part.
    $domain = count($host_parts) > 1 ? $host_parts[count($host_parts) - 2] : $host;

    // List of popular social media domains to check against
    // that have icons in the theme.
    $social_media_domains = [
      'discord',
      'facebook',
      'instagram',
      'linkedin',
      'snapchat',
      'tiktok',
      'twitch',
      'twitter',
      'x',
      'youtube',
    ];

    // Default service structure.
    $service = [
      'social_media_name' => $social_media_link_uri,
      'social_media_icon' => $domain,
      'social_media_url' => $social_media_link_uri,
    ];

    // Check if the extracted domain is in the allowed list.
    if (in_array($domain, $social_media_domains, TRUE)) {
      $service['social_media_name'] = match ($domain) {
        'discord' => 'Discord',
        'facebook' => 'Facebook',
        'instagram' => 'Instagram',
        'linkedin' => 'LinkedIn',
        'snapchat' => 'Snapchat',
        'tiktok' => 'TikTok',
        'twitch' => 'Twitch',
        'twitter', 'x' => 'X',
        'youtube' => 'YouTube',
        default => $service['social_media_name'],
      };
    }
    else {
      $service['social_media_icon'] = 'link';
    }

    return $service;
  }

}
