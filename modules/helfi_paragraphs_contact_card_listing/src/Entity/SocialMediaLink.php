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

}
