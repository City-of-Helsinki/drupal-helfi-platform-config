<?php

declare(strict_types = 1);

namespace Drupal\helfi_events;

use Drupal\Core\Url;

/**
 * Class for retrieving data from LinkedEvents Api.
 */
class LinkedEvents extends EventsApiBase {
  protected const API_URL = 'https://api.hel.fi/linkedevents/v1/';

  /**
   * Form url for getting events from api.
   *
   * @param array $options
   *   Filters as key = value array.
   *
   * @return string
   *   Resulting api url with params a query string
   */
  public function getEventsRequest(array $options = []) : string {
    $url = Url::fromUri(self::API_URL . 'event');

    $defaultOptions = [
      'event_type' => 'General',
      'format' => 'json',
      'include' => 'keywords,location',
      'page' => 1,
      'page_size' => 12,
      'sort' => 'end_time',
      'start' => 'now',
      'super_event_type' => 'umbrella,none',
      'language' => 'fi',
    ];

    $options = array_merge($defaultOptions, $options);

    if (!isset($options['all_ongoing_AND'])) {
      $options['all_ongoing'] = 'true';
    }

    $url->setOption('query', $options);

    return $url->toString();
  }

}
