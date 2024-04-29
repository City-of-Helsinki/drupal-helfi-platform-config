<?php

declare(strict_types = 1);

namespace Drupal\helfi_paragraphs_news_list\Entity\ExternalEntity;

use Drupal\external_entities\Entity\ExternalEntity;

final class News extends ExternalEntity {

  public function getPublishedAt() : int {
    return (int) $this->get('published_at')->value;
  }

  public function getNodeUrl() : string {
    return $this->get('node_url')->value;
  }

  public function getShortTitle() : string {
    return $this->get('short_title')->value;
  }

}
