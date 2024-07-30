<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_contact_card_listing\Entity;

use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;
use Symfony\Contracts\Translation\TranslatorTrait;

/**
 * Bundle class for contact_card paragraph.
 */
class ContactCard extends Paragraph implements ParagraphInterface {

  use TranslatorTrait;

  /**
   * Get the heading level.
   *
   * @return string|null
   *   Level of heading.
   */
  public function getHeadingLevel(): ?string {
    /** @var \Drupal\paragraphs\Entity\ParagraphInterface $parent */
    $parent = $this->getParentEntity();
    if (
      $parent->hasField('field_title') &&
      !$parent->get('field_title')->isEmpty()
    ) {
      return 'h3';
    }
    return NULL;
  }

}
