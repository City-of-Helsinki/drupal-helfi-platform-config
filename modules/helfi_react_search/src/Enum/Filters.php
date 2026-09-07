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
  case SearchTerm = 'field_search_term';
  case TargetGroups = 'field_target_groups';

  // Return the Drupal setting name for the filter. Defaults to the field name,
  // but allows overrides where needed.
  public function drupalSettingName(): string {
    return match ($this) {
      self::TargetGroups => 'useTargetGroupFilter',
      default => $this->value,
    };
  }
}
