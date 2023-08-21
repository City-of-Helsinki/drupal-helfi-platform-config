<?php

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
      return ucfirst($this->get('field_icon')->icon);
    }
    return NULL;
  }

}
