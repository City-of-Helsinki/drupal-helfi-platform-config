<?php

declare(strict_types=1);

namespace Drupal\helfi_react_search\Entity;

use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Bundle class for Hero -paragraph.
 */
class EventList extends Paragraph implements ParagraphInterface {

  /**
   * Get list of enabled filter keywords.
   *
   * @param string $langcode
   *   Keyword translation langcode.
   *
   * @return \Drupal\taxonomy\TermInterface[]
   *   Enabled keyword.
   */
  public function getFilterKeywords(string $langcode) : array {
    $keywords = [];

    /** @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $field_keywords */
    $field_keywords = $this->get('field_filter_keywords');

    /** @var \Drupal\taxonomy\TermInterface $term */
    foreach ($field_keywords->referencedEntities() as $term) {
      if ($term->hasTranslation($langcode)) {
        $keywords[] = $term->getTranslation($langcode);
      }
    }

    return $keywords;
  }

}
