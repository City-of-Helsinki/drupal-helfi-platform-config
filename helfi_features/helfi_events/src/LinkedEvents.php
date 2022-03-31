<?php

namespace Drupal\helfi_events;

use Drupal\Core\Url;

class LinkedEvents extends EventsApiBase {
  const API_URL = 'https://api.hel.fi/linkedevents/v1/';

  /**
   * Form url for gettinng events from api
   * 
   * @param array $params
   *   Filters as key = value array
   * @return string
   *   Resulting api url with params a query string
   */
  public function getEventsRequest($options = []) {
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
      'language' => 'fi'
    ];

    $options = array_merge($defaultOptions, $options);

    if (isset($options['all_ongoing_AND'])) {
      $options['all_ongoing'] = 'true';
    }
    
    $url->setOption('query', $options);

    return $url->toString();
  }
}
