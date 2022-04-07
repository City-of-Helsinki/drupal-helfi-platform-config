<?php

declare(strict_types = 1);

namespace Drupal\helfi_news_feed\Entity;

use Drupal\Core\Field\Plugin\Field\FieldType\StringItem;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Bundle class for 'news_list' paragraph.
 */
final class NewsFeedParagraph extends Paragraph {

  /**
   * Gets the tags.
   *
   * @return array
   *   An array of tags.
   */
  public function getTags() : array {
    return array_map(function (StringItem $value) {
      return $value->value;
    }, iterator_to_array($this->get('field_tags')));
  }

  /**
   * Gets the limit.
   *
   * Defines how many items is shown.
   *
   * @return int
   *   The limit.
   */
  public function getLimit() : int {
    $limit = (int) $this->get('field_limit')->value;

    return $limit > 0 ? $limit : 1;
  }

}
