<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_news_list\Entity;

use Drupal\paragraphs\Entity\Paragraph;

/**
 * Bundle class for 'news_list' paragraph.
 */
final class NewsFeedParagraph extends Paragraph {

  /**
   * Gets the defined tags.
   *
   * @return string[]
   *   An array of tags.
   */
  public function getTags() : array {
    return $this->get('field_helfi_news_tags')->getValue() ?? [];
  }

  /**
   * Gets the UUIDs for given external entity term.
   *
   * The terms id contain uuid and langcode, but we only care about
   * the UUID when filtering by term.
   *
   * @param array $values
   *   The values to parse.
   *
   * @return array
   *   An array of UUIDs.
   */
  private function getTermUuid(array $values) : array {
    return array_map(fn (array $item) => explode(':', $item['target_id'])[0], $values);
  }

  /**
   * Gets the UUIDs for given tags.
   *
   * @return array
   *   An array of tags UUIDs.
   */
  public function getTagsUuid() : array {
    return $this->getTermUuid($this->getTags());
  }

  /**
   * Gets the defined groups.
   *
   * @return string[]
   *   An array of groups.
   */
  public function getGroups() : array {
    return $this->get('field_helfi_news_groups')->getValue() ?? [];
  }

  /**
   * Gets the UUIDs for given groups.
   *
   * @return array
   *   An array of group UUIDs.
   */
  public function getGroupsUuid() : array {
    return $this->getTermUuid($this->getGroups());
  }

  /**
   * Gets the defined neighbourhoods.
   *
   * @return string[]
   *   An array of neighbourhoods.
   */
  public function getNeighbourhoods() : array {
    return $this->get('field_helfi_news_neighbourhoods')->getValue() ?? [];
  }

  /**
   * Gets the UUIDs for given neighbourhoods.
   *
   * @return array
   *   An array of neighbourhood UUIDs.
   */
  public function getNeighbourhoodsUuids() : array {
    return $this->getTermUuid($this->getNeighbourhoods());
  }

  /**
   * Gets the limit.
   *
   * Defines how many items are shown.
   *
   * @return int
   *   The limit.
   */
  public function getLimit() : int {
    $limit = (int) $this->get('field_news_limit')->value;

    return $limit > 0 ? $limit : 1;
  }

  /**
   * Gets the paragraph title.
   *
   * @return string
   *   The title.
   */
  public function getTitle() : string {
    return $this->get('field_news_list_title')->value;
  }

  /**
   * Gets the paragraph description.
   *
   * @return string
   *   The description.
   */
  public function getDescription() : string {
    return $this->get('field_news_list_description')->value;
  }

  /**
   * Allowed values function for the news list paragraph's field_news_limit.
   *
   * @return int[]
   *   The list of allowed values.
   */
  public static function getNewsLimitValues(): array {
    return [4 => 4, 6 => 6, 8 => 8];
  }

}
