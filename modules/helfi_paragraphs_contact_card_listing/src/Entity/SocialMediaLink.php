<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_contact_card_listing\Entity;

use Drupal\helfi_paragraphs_contact_card_listing\SocialMediaServiceParserTrait;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Bundle class for social_media_link paragraph.
 */
class SocialMediaLink extends Paragraph implements ParagraphInterface {

  use SocialMediaServiceParserTrait;

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

}
