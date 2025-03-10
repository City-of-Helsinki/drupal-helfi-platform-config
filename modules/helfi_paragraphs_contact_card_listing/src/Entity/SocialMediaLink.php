<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_contact_card_listing\Entity;

use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Bundle class for social_media_link paragraph.
 */
class SocialMediaLink extends Paragraph implements ParagraphInterface {

  /**
   * Get icon name.
   *
   * @return string|null
   *   Name of the icon.
   */
  public function getIconName(): ?string {
    if (!$this->get('field_icon')->isEmpty()) {
      /** @var \Drupal\hdbt_admin_tools\Plugin\Field\FieldType\SelectIcon $field */
      $field = $this->get('field_icon');
      return ucfirst($field->icon);
    }
    return NULL;
  }

  /**
   * Get the Social Media service.
   *
   * @return array|null
   *   Social media service array.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function getSocialMedia(): ?array {
    if (!$this->get('field_social_media_link')->isEmpty()) {
      /** @var \Drupal\link\LinkItemInterface $social_media_link */
      $social_media_link = $this->get('field_social_media_link')->first();
      $social_media_link_uri = $social_media_link->getUrl()->getUri();

      if (empty($social_media_link_uri)) {
        return NULL;
      }

      // Process Social Media uri to service.
      return $this->processSocialMediaDomain($social_media_link_uri);
    }

    return NULL;
  }

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
      'x',
      'youtube',
    ];

    $service = [
      'social_media_name' => $social_media_link_uri,
      'social_media_icon' => $domain,
      'social_media_url' => $social_media_link_uri,
    ];

    // Check if the extracted domain is in the allowed list.
    if (in_array($domain, $social_media_domains, TRUE)) {
      switch ($domain) {
        case 'discord':
          $service['social_media_name'] = 'Discord';
          break;

        case 'facebook':
          $service['social_media_name'] = 'Facebook';
          break;

        case 'instagram':
          $service['social_media_name'] = 'Instagram';
          break;

        case 'linkedin':
          $service['social_media_name'] = 'LinkedIn';
          break;

        case 'snapchat':
          $service['social_media_name'] = 'Snapchat';
          break;

        case 'tiktok':
          $service['social_media_name'] = 'TikTok';
          break;

        case 'twitch':
          $service['social_media_name'] = 'Twitch';
          break;

        case 'twitter':
          $service['social_media_name'] = 'X';
          break;

        case 'x':
          $service['social_media_name'] = 'X';
          $service['social_media_icon'] = 'twitter';
          break;

        case 'youtube':
          $service['social_media_name'] = 'YouTube';
          break;

        default:
          $service['social_media_icon'] = 'link';
          break;

      }
    }

    return $service;
  }

}
