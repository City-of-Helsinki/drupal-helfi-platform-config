<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_phasing\Entity;

use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Bundle class for phasing paragraph.
 */
class Phasing extends Paragraph implements ParagraphInterface {

  /**
   * Get heading level for title.
   *
   * @return string
   *   heading level.
   */
  public function getHeadingLevel() {
    $headingLevel = $this->get('field_phasing_title_level')
      ->getString();

    return "h$headingLevel";
  }

}
