<?php

declare(strict_types=1);

namespace Drupal\helfi_react_search;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

/**
 * Class for retrieving data from LinkedEvents Api.
 */
class LinkedEvents extends EventsApiBase {
  public const BASE_URL = 'https://tapahtumat.hel.fi';
  protected const API_URL = 'https://api.hel.fi/linkedevents/v1/';

  /**
   * Class constructor.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $dataCache
   *   The cache service.
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The HTTP client.
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger channel.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   */
  public function __construct(
    private CacheBackendInterface $dataCache,
    private ClientInterface $httpClient,
    private LoggerInterface $logger,
    private LanguageManagerInterface $languageManager
  ) {}

  /**
   * Max age for cache.
   */
  public function getCacheMaxAge() : int {
    return time() + 60 * 60 * 8;
  }

  /**
   * Gets the cache key for given id.
   *
   * @param string $id
   *   The id.
   *
   * @return string
   *   The cache key.
   */
  protected function getCacheKey(string $id) : string {
    $id = preg_replace('/[^a-z0-9_]+/s', '_', $id);

    return sprintf('linked-events-%s', $id);
  }

  /**
   * Gets cached data for given id.
   *
   * @param string $id
   *   The id.
   *
   * @return array|null
   *   The cached data or null.
   */
  protected function getFromCache(string $id) : ? array {
    $key = $this->getCacheKey($id);

    if ($data = $this->dataCache->get($key)) {
      return $data->data;
    }
    return NULL;
  }

  /**
   * Sets the cache.
   *
   * @param string $id
   *   The id.
   * @param mixed $data
   *   The data.
   */
  protected function setCache(string $id, $data) : void {
    $key = $this->getCacheKey($id);
    $this->dataCache->set($key, $data, $this->getCacheMaxAge(), []);
  }

  /**
   * Form url for getting events from api.
   *
   * @param array $options
   *   Filters as key = value array.
   * @param string $pageSize
   *   How many events to load in a page.
   *
   * @return string
   *   Resulting api url with params a query string
   */
  public function getEventsRequest(array $options = [], string $pageSize = '3') : string {
    $url = Url::fromUri(self::API_URL . 'event');

    $defaultOptions = [
      'event_type' => 'General',
      'format' => 'json',
      'include' => 'keywords,location',
      'page' => 1,
      'page_size' => $pageSize,
      'sort' => 'end_time',
      'start' => 'now',
      'super_event_type' => 'umbrella,none',
      'language' => $this->languageManager
        ->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)
        ->getId(),
    ];

    $options = array_merge($defaultOptions, $options);

    if (!isset($options['all_ongoing_AND'])) {
      $options['all_ongoing'] = 'true';
    }

    $url->setOption('query', $options);

    return $url->toString();
  }

  /**
   * Return places from cache or generate list of them.
   *
   * @param string $event_url
   *   The Api url for events.
   *
   * @return array
   *   Array of all possible places.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getPlacesList(string $event_url) : array {
    $url = $this->formatPlacesUrl($event_url);

    if ($data = $this->getFromCache($url)) {
      return $data;
    }

    $result = [];

    // Get location IDs from event URL.
    $parsed_url = UrlHelper::parse($event_url);
    $places = [];
    if (!empty($parsed_url['query']['location'])) {
      $places = explode(',', $parsed_url['query']['location']);
    }

    try {
      $response = $this->httpClient->request('GET', $url);
      $body = json_decode($response->getBody()->getContents());
      $next = $body->meta->next;
      $data = $body->data;

      do {
        foreach ($data as $item) {
          // Bail if no location data.
          if (!isset($item->id) || !isset($item->name)) {
            continue;
          }

          // Trim list of locations based on original IDs.
          // Has to be done here because places API doesn't accept lists of ids.
          if (!empty($places) && !in_array($item->id, $places)) {
            continue;
          }

          if (!array_key_exists($item->id, $result)) {
            $result[$item->id] = $item;
          }
        }

        if ($next) {
          $response = $this->httpClient->request('GET', $next);
          $body = json_decode($response->getBody()->getContents());
          $next = $body->meta->next;
          $data = $body->data;
        }
        else {
          $data = NULL;
        }
      } while ($data && count($data) > 0);

      $this->setCache($url, $result);
    }
    catch (GuzzleException $e) {
      $this->logger->error('Request failed with error: ' . $e->getMessage());
    }

    return $result;
  }

  /**
   * Format places API URL with query options from events API URL.
   *
   * - Currently only 'division' option is useful.
   * - 'has_upcoming_events' option is used to limit results (from 2k to 700).
   * - Places API doesn't accept a list of IDs, so we have to get all results.
   * - This is usually still faster than querying each place individually.
   *
   * @param string $event_url
   *   Event API URL.
   *
   * @return string
   *   Formatted places API URL with query options from event URL.
   */
  public function formatPlacesUrl(string $event_url): string {
    $url = Url::fromUri(self::API_URL . 'place');

    // Add default options to reduce amount of requests.
    $defaultOptions = [
      'has_upcoming_events' => 'true',
      'sort' => 'name',
      'page_size' => '100',
    ];

    // Pick up options from event URL.
    $options = [];
    $parsed_url = UrlHelper::parse($event_url);
    if (isset($parsed_url['query']['division'])) {
      $options['division'] = $parsed_url['query']['division'];
    }

    // Add locations to URL for caching purposes.
    if (isset($parsed_url['query']['locations'])) {
      $options['locations'] = $parsed_url['query']['locations'];
    }

    $options = array_merge($defaultOptions, $options);
    $url->setOption('query', $options);

    return $url->toString();
  }

}
