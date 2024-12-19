<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_accordion\Entity;

use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Bundle class for accordion paragraph.
 */
class Accordion extends Paragraph implements ParagraphInterface {

  /**
   * Get title level.
   *
   * @return string
   *   The title level.
   */
  public function getTitleLevel(): string {
    return $this->get('field_accordion_title_level')->getString();
  }

}
