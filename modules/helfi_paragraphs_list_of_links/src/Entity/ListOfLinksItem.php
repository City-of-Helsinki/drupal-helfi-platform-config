<?php

namespace Drupal\helfi_paragraphs_list_of_links\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Bundle class for list_of_links paragraph.
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
      ->get('field_list_of_links_design')
      ->value;
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

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    // #UHF-9534 Remove media entity when design doesn't support media.
    if ($this->getDesign() != 'with-image') {
      $this->set('field_list_of_links_image', NULL);
    }
    parent::preSave($storage);
  }

}
