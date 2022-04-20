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
   * A helper function to get multifield values.
   *
   * @param string $field
   *   The field name.
   *
   * @return array
   *   The term field values.
   */
  private function getUnlimitedStringFieldValue(string $field) : array {
    return array_map(function (StringItem $value) {
      return $value->value;
    }, iterator_to_array($this->get($field)));
  }

  /**
   * Gets the defined tags.
   *
   * @return string[]
   *   An array of tags.
   */
  public function getTags() : array {
    return $this->getUnlimitedStringFieldValue('field_tags');
  }

  /**
   * Gets the defined groups.
   *
   * @return string[]
   *   Anb array of groups.
   */
  public function getGroups() : array {
    return $this->getUnlimitedStringFieldValue('field_groups');
  }

  /**
   * Gets the defined neighbourhoods.
   *
   * @return string[]
   *   An array of neighbourhoods.
   */
  public function getNeighbourhoods() : array {
    return $this->getUnlimitedStringFieldValue('field_neighbourhoods');
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
