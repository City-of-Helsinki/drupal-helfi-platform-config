<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_phasing\Entity;

use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Bundle class for phasing_item paragraph.
 */
class PhasingItem extends Paragraph implements ParagraphInterface {

  /**
   * Get the heading level, for examle h4.
   *
   * @return string
   *   Heading level.
   */
  public function getHeadingLevel(): string {
    $headingLevel = $this->getParentEntity()
      ->get('field_phasing_item_title_level')
      ->getString();

    return "h$headingLevel";
  }

  /**
   * The value if phase numbers should be shown or not.
   *
   * @return string
   *   Value of boolean.
   */
  public function getShowNumbers(): string {
    return $this->getParentEntity()
      ->get('field_show_phase_numbers')
      ->value;
  }

}
