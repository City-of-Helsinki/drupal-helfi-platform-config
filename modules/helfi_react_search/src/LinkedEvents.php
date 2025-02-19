<?php

declare(strict_types=1);

namespace Drupal\helfi_react_search;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use Drupal\helfi_api_base\ApiClient\ApiClient;
use Drupal\helfi_api_base\ApiClient\ApiFixture;
use Drupal\helfi_api_base\ApiClient\ApiResponse;
use Drupal\helfi_api_base\ApiClient\CacheValue;
use Drupal\helfi_api_base\Cache\CacheKeyTrait;
use Drupal\helfi_react_search\Enum\CategoryKeywords;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Class for retrieving data from LinkedEvents Api.
 */
class LinkedEvents {

  public const BASE_URL = 'https://tapahtumat.hel.fi';
  public const FIXTURE_NAME = 'fixture-linked-events';
  protected const API_URL = 'https://api.hel.fi/linkedevents/v1/';

  /**
   * Cache events data for eight hours.
   *
   * The response cache is flushed by 'helfi_navigation_menu_queue'
   * queue worker.
   */
  public const TTL = 28800;

  /**
   * Should the fixture data be used.
   *
   * @var bool|string
   */
  protected bool|string $useFixtures = FALSE;

  /**
   * The previous exception.
   *
   * @var \Exception|null
   */
  private ?\Exception $previousException = NULL;

  use CacheKeyTrait;

  /**
   * Class constructor.
   *
   * @param \Drupal\helfi_api_base\ApiClient\ApiClient $client
   *   The helfi api base api client.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel.
   */
  public function __construct(
    #[Autowire(service: 'helfi_react_search.api_client')] private ApiClient $client,
    private readonly LanguageManagerInterface $languageManager,
    #[Autowire(service: 'logger.channel.helfi_react_search')] private readonly LoggerInterface $logger,
  ) {
  }

  /**
   * Allow cache to be bypassed.
   *
   * @return $this
   *   The self.
   */
  public function withBypassCache() : self {
    $instance = clone $this;
    $instance->client = $this->client->withBypassCache();
    return $instance;
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
    if ($this->useFixtures) {
      return $this->getFixturePath($this->useFixtures);
    }

    // Linked events URLs should end with '/' (URLs without '/' are redirect).
    $url = Url::fromUri(self::API_URL . 'event/');

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
   * Use cache to fetch an external menu from Etusivu instance.
   *
   * @param string $event_url
   *   The event url.
   *
   * @return \Drupal\helfi_api_base\ApiClient\ApiResponse
   *   The JSON object representing the linked events
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function get(string $event_url) : ApiResponse {

    // Format places API URL with query options from events API URL.
    $url = $this->formatPlacesUrl($event_url);

    // Create cache key for linked events.
    $key = $this->getCacheKey(sprintf('linked-events:%s', $url));

    return $this->client->cache(
      $key,
      function () use ($url) {
        // Fixture is used in tests and in local if the API connection fails.
        $fixture = $this->getFixturePath(self::FIXTURE_NAME);

        // Return the mock data if fixture is set via URL field.
        if ($this->useFixtures) {
          $response = ApiFixture::requestFromFile($fixture);
        }
        else {
          $response = $this->client->makeRequestWithFixture($fixture, 'GET', $url);
        }

        return new CacheValue(
          $response,
          $this->client->cacheMaxAge(self::TTL),
          [sprintf('linked-events:%s', $url)],
        );
      }
    )->response;
  }

  /**
   * Parse response.
   *
   * @param \Drupal\helfi_api_base\ApiClient\ApiResponse $apiResponse
   *   The JSON object representing the linked events.
   * @param array $places
   *   List of places.
   *
   * @return array
   *   Array of linked events
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  protected function parseResponse(ApiResponse $apiResponse, array $places) : array {
    $result = [];

    try {
      $response = $this->getDataFromResponse($apiResponse);
      $next = $response->meta->next ?? NULL;
      $data = $response->data ?? NULL;

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
          $next_response = $this->getDataFromResponse($this->get($next));
          $next = $next_response->meta->next;
          $data = $next_response->data;
        }
        else {
          $data = NULL;
        }
      } while ($data && count($data) > 0);
    }
    catch (\Exception $e) {
      $this->logger->error('Request failed with error: ' . $e->getMessage());
    }
    return $result;
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
    $result = [];

    try {
      $places = [];

      if ($this->useFixtures) {
        $response = $this->get($this->useFixtures);
      }
      else {
        $response = $this->get($this->formatPlacesUrl($event_url));

        // Get location IDs from event URL.
        $parsed_url = UrlHelper::parse($event_url);
        if (!empty($parsed_url['query']['location'])) {
          $places = explode(',', $parsed_url['query']['location']);
        }
      }

      // Parse response.
      $result = $this->parseResponse($response, $places);
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

    // Add page to the parsed URL.
    if (isset($parsed_url['query']['page'])) {
      $options['page'] = $parsed_url['query']['page'];
    }

    $options = array_merge($defaultOptions, $options);
    $url->setOption('query', $options);

    return $url->toString();
  }

  /**
   * Parse query params from request url.
   *
   * @param string $url
   *   Tapahtumat.hel.fi url.
   *
   * @return array
   *   Array of params.
   */
  public function parseParams(string $url) : array {
    if (str_contains($url, 'fixture')) {
      $this->useFixtures = self::FIXTURE_NAME;
      return [];
    }

    $parsed = UrlHelper::parse($url);
    $params = [];

    if (!empty($parsed) && isset($parsed['query'])) {
      foreach ($parsed['query'] as $key => $param) {
        switch ($key) {
          case 'categories':
            $this->handleKeywords($params, $this->categoriesToKeywords($param));
            break;

          case 'start':
            $now = strtotime('now');
            if (strtotime($param) < $now) {
              $params[$key] = 'now';
            }
            else {
              $params[$key] = $param;
            }
            break;

          case 'divisions':
            $params['division'] = $param;
            break;

          case 'places':
            $params['location'] = $param;
            break;

          case 'dateTypes':
            $dateTypes = explode(',', $param);
            foreach ($dateTypes as $dateType) {
              switch ($dateType) {
                case 'today':
                  $params['end'] = 'today';
                  break;

                case 'tomorrow':
                  $params['start'] = date('Y-m-d', strtotime('tomorrow'));
                  $params['end'] = date('Y-m-d', strtotime('tomorrow'));
                  break;

                case 'this_week':
                  $params['end'] = date('Y-m-d', strtotime('next Sunday'));
                  break;

                case 'weekend':
                  $params['start'] = date('Y-m-d', strtotime('next Saturday'));
                  $params['end'] = date('Y-m-d', strtotime('next Sunday'));
                  break;

                default:
                  break;
              }
            }
            break;

          case 'text':
            $this->handleTextSearch($params, $param);
            break;

          case 'isFree':
            $params['is_free'] = $param;
            break;

          case 'onlyEveningEvents':
            if ($param === 'true') {
              $params['starts_after'] = 16;
            }
            break;

          case 'onlyChildrenEvents':
            if ($param === 'true') {
              $params['keyword_AND'] = CategoryKeywords::CHILDREN;
            }
            break;

          case 'onlyRemoteEvents':
            $params['internet_based'] = 'true';
            break;

          default:
            $params[$key] = $param;
            break;
        }
      }
    }

    return $params;
  }

  /**
   * Transform categories to an array of keywords for the API.
   *
   * @param string $categories
   *   Event categories.
   *
   * @return string
   *   Resulting json-encoded string of keywords
   */
  protected function categoriesToKeywords(string $categories) : string {
    $keywords = [];

    foreach (explode(',', $categories) as $category) {
      $map = match ($category) {
        'culture' => CategoryKeywords::CULTURE,
        'movie' => CategoryKeywords::MOVIE,
        'sport' => CategoryKeywords::SPORT,
        'nature' => CategoryKeywords::NATURE,
        'museum' => CategoryKeywords::MUSEUM,
        'music' => CategoryKeywords::MUSIC,
        'influence' => CategoryKeywords::INFLUENCE,
        'food' => CategoryKeywords::FOOD,
        'dance' => CategoryKeywords::DANCE,
        'theatre' => CategoryKeywords::THEATRE,
      };

      $keywords = array_merge($map, $keywords);
    }

    return implode(',', $keywords);
  }

  /**
   * Handle text search.
   *
   * Some search phrases can be replaced with a keyword making the API request
   * much more performant.
   *
   * @param array $params
   *   The parameters. Passed by reference.
   * @param string $text
   *   The search text to handle.
   */
  protected function handleTextSearch(&$params, string $text) : void {
    $keyword = match ($text) {
      'tyollisyys', 'työllisyys' => CategoryKeywords::WORKLIFE,
      default => NULL,
    };

    if ($keyword) {
      $this->handleKeywords($params, $keyword);
    }
    else {
      $params['all_ongoing_AND'] = $text;
    }
  }

  /**
   * Handle keywords.
   *
   * @param array $params
   *   The parameters. Passed by reference.
   * @param string $new_keywords
   *   The keywords string to add to the parameters.
   */
  protected function handleKeywords(&$params, string $new_keywords) : void {
    // Keyword parameter is a comma-separated string. If it's empty, set it to
    // the new keywords. Otherwise, append the new keywords.
    $params['keyword'] = !empty($params['keyword']) ? $params['keyword'] . ',' . $new_keywords : $new_keywords;
  }

  /**
   * Get fixture for javascript.
   *
   * @return mixed
   *   Returns FALSE if useFixtures is FALSE, otherwise returns the JSON object.
   */
  public function getFixture() : mixed {
    if ($this->useFixtures) {
      $json = file_get_contents($this->getFixturePath($this->useFixtures));
      if ($json) {
        return json_decode($json);
      }
    }
    return FALSE;
  }

  /**
   * Get path to fixture.
   *
   * @param string $url
   *   URL to parse.
   *
   * @return string
   *   Returns path to fixture.
   */
  protected function getFixturePath(string $url) : string {
    return vsprintf('%s/../fixtures/%s.json', [
      __DIR__,
      str_replace('/', '-', ltrim($url, '/')),
    ]);
  }

  /**
   * Get data from response.
   *
   * @param \Drupal\helfi_api_base\ApiClient\ApiResponse $response
   *   The API response object.
   *
   * @return object
   *   Returns data from response.
   */
  protected function getDataFromResponse(ApiResponse $response) : object {
    return $response->data;
  }

}
