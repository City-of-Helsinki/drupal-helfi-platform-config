<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_list_of_links\Entity;

use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Bundle class for list_of_links_item paragraph.
 */
class ListOfLinksItem extends Paragraph implements ParagraphInterface {

  /**
   * Get the design.
   *
   * @return string
   *   Design.
   */
  public function getDesign(): string {
    return $this->getParentEntity()
      ?->get('field_list_of_links_design')
      ?->value ?? 'without-image';
  }

  /**
   * Has the title.
   *
   * @return bool
   *   Has title.
   */
  public function hasTitle(): bool {
    return !$this->getParentEntity()
      ->get('field_list_of_links_title')
      ->isEmpty();
  }

}
