<?php

declare(strict_types=1);

namespace Drupal\helfi_react_search\Enum;

/**
 * Available filters for event list paragraph.
 */
enum Filters: string {
  case Locations = 'field_event_location';
  case EventTime = 'field_event_time';
  case FreeEvents = 'field_free_events';
  case RemoteEvents = 'field_remote_events';
  case Language = 'field_language';
}
