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
   * Check if event has ended and return result.
   *
   * @return bool
   *   The resulting boolean.
   */
  public function hasEnded() : bool {
    $end_time = $this->get('end_time')?->value;

    if (!$end_time) {
      return false;
    }

    $datetime = new DrupalDateTime($end_time);
    return $datetime->format('U') < time();
  }

}
