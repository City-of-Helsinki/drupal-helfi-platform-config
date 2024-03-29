<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_hero\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Bundle class for Hero -paragraph.
 */
class Hero extends Paragraph implements ParagraphInterface {

  /**
   * Get paragraph design.
   *
   * @return string
   *   The design.
   */
  public function getDesign(): string {
    return $this->get('field_hero_design')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    if ($this->getDesign() == 'without-image-left') {
      $this->set('field_hero_image', NULL);
    }
    parent::preSave($storage);
  }

}
