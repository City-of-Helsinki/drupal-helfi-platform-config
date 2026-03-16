<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_curated_event_list\Entity;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\external_entities\Entity\ExternalEntity;

/**
 * A bundle class for LinkedEvents Events external entities.
 */
final class LinkedEventsEvent extends ExternalEntity {

  /**
   * Gets the end time.
   *
   * @return \Drupal\Core\Datetime\DrupalDateTime|null
   *   The end time or null.
   */
  public function getEndTime(): ?DrupalDateTime {
    $endTime = $this->get('end_time')?->value;

    if (!$endTime) {
      return NULL;
    }
    return new DrupalDateTime($endTime);
  }

}
