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
   * Gets the defined groups.
   *
   * @return string[]
   *   Anb array of groups.
   */
  public function getGroups() : array {
    return $this->get('field_helfi_news_groups')->getValue() ?? [];
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

}
