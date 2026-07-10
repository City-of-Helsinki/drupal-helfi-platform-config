<?php

declare(strict_types=1);

namespace Drupal\helfi_base_content\Entity;

use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Bundle class for from_library paragraph.
 */
class ParagraphFromLibrary extends Paragraph implements ParagraphInterface {

  /**
   * Checks whether the referenced library item is published.
   *
   * @return bool
   *   TRUE if the referenced library item exists and is not published.
   */
  public function isNotPublished(): bool {
    $libraryItem = $this->get('field_reusable_paragraph')->entity;

    return $libraryItem instanceof EntityPublishedInterface && !$libraryItem->isPublished();
  }

}
