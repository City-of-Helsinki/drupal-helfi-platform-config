<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_curated_event_list\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\external_entities\Entity\ExternalEntity;

/**
 * A bundle class for LinkedEvents Events external entities.
 */
final class LinkedEventsEvent extends ExternalEntity {

  /**
   * Gets the current time.
   *
   * @return int
   *   The current time.
   */
  private function getCurrentTime(): int {
    return \Drupal::time()->getCurrentTime();
  }

  /**
   * Checks if the event has expired.
   *
   * @return bool
   *   TRUE if event has expired, FALSE if not.
   */
  public function hasEnded(): bool {
    if ($endTime = $this->getEndTime()?->getTimestamp()) {
      return $endTime < $this->getCurrentTime();
    }
    return FALSE;
  }

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
    // The API returns UTC+0 dates.
    return new DrupalDateTime($endTime, 'UTC');
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): ?int {
    // Cache until the event ends + 5 seconds to give it some
    // buffer.
    if ($endTime = $this->getEndTime()?->getTimestamp()) {
      return ($endTime - $this->getCurrentTime()) + 5;
    }
    // Cache permanently if event has ended or has no
    // end time.
    return Cache::PERMANENT;
  }

}
