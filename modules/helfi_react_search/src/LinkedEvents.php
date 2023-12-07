<?php

declare(strict_types = 1);

namespace Drupal\helfi_react_search;

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
   * This function causes slow page load if it fetches places from Linked Events
   * API with thousands of events without cache. Function is used in preprocess
   * function. It's a known issue but is it possible to get rid of the slowness
   * without cache and thousands of events? If this causes problems in the
   * future, the function can be re-thinked. More info from old ticket:
   * https://helsinkisolutionoffice.atlassian.net/browse/UHF-8163
   *
   * @param string $url
   *   The Api url for events.
   *
   * @return array
   *   Array of all possible places.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getPlacesList($url) : array {
    // Remove keywords from api url not to get detailed keyword data for places.
    $url = str_replace('keywords%2C', '', $url);

    if ($data = $this->getFromCache($url)) {
      return $data;
    }

    $result = [];

    // Set max page size to reduce amount of requests.
    $transformedUrl = Url::fromUri($url);
    $transformedUrl->setOption('query', ['page_size' => '100']);
    $transformedUrl = $transformedUrl->toString();

    try {
      $response = $this->httpClient->request('GET', $transformedUrl);
      $body = json_decode($response->getBody()->getContents());
      $next = $body->meta->next;
      $data = $body->data;

      do {
        foreach ($data as $item) {
          // Bail if no location data.
          if (!isset($item->location) || !isset($item->location->id)) {
            continue;
          }

          if (!array_key_exists($item->location->id, $result)) {
            $result[$item->location->id] = $item->location;
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

}
