<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_news_list\Entity\ExternalEntity;

use Drupal\external_entities\Entity\ExternalEntity;

/**
 * A bundle class for Helfi: news external entities.
 */
final class News extends ExternalEntity {

  /**
   * Gets the published at timestamp.
   *
   * @return int
   *   The published at unix-timestamp.
   */
  public function getPublishedAt() : int {
    return (int) $this->get('published_at')->value;
  }

  /**
   * Gets the node URL.
   *
   * @return string
   *   The node URL.
   */
  public function getNodeUrl() : string {
    return $this->get('node_url')->value;
  }

  /**
   * Gets the short title.
   *
   * @return string|null
   *   The short title.
   */
  public function getShortTitle() : ?string {
    return $this->get('short_title')->value;
  }

}
