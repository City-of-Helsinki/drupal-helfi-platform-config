<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_news_list\Entity\ExternalEntity;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\external_entities\Entity\ExternalEntity;

/**
 * A bundle class for external taxonomy terms.
 */
final class Term extends ExternalEntity {

  /**
   * Gets the term ID.
   *
   * @return int
   *   The term id.
   */
  public function getTid() : int {
    return (int) $this->get('tid')->value;
  }

}
