<?php

declare(strict_types=1);

namespace Drupal\helfi_react_search;

use Drupal\Component\Utility\UrlHelper;
use Drupal\helfi_react_search\DTO\LinkedEventsItem;
use Drupal\helfi_react_search\Entity\EventList;
use Drupal\helfi_react_search\Enum\CategoryKeywords;
use Drupal\helfi_react_search\Enum\EventCategory;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Utils;
use Symfony\Component\Serializer\Exception\RuntimeException;

/**
 * Event list update helper.
 *
 * This service is for an update hook and can
 * be removed in the later versions.
 */
class EventListUpdateHelper {

  public function __construct(
    private readonly ClientInterface $client,
  ) {
  }

  /**
   * Migrates `field_api_url` to new format.
   *
   * @param \Drupal\helfi_react_search\Entity\EventList $eventList
   *   Event list to update.
   *
   * @return bool
   *   If the paragraph was modified.
   */
  public function migrateApiUrl(EventList $eventList): bool {
    if (!$eventList->hasField('field_api_url')) {
      return FALSE;
    }

    // @phpstan-ignore-next-line
    $url = $eventList->get('field_api_url')->uri;
    $query = UrlHelper::parse($url)['query'] ?? [];

    $modified = FALSE;
    $modified |= $this->migratePlaces($eventList, $query);
    $modified |= $this->migrateKeywords($eventList, $query);
    $modified |= $this->migrateCategory($eventList, $query);
    $modified |= $this->migrateCustomQuery($eventList, $query);

    return (bool) $modified;
  }

  /**
   * Migrate places.
   */
  private function migrateKeywords(EventList $eventList, array $query): bool {
    if (empty($query['keyword'])) {
      return FALSE;
    }

    $result = [];
    ['keyword' => $keywords] = $query;
    foreach (explode(',', $keywords) as $keyword) {
      try {
        $response = $this->client
          ->request('GET', "https://api.hel.fi/linkedevents/v1/keyword/$keyword/");

        $item = Utils::jsonDecode($response->getBody()->getContents(), assoc: TRUE);

        $result[] = json_encode(new LinkedEventsItem($item['id'], $item['name']));
      }
      catch (GuzzleException) {
        // Ignore error.
      }
    }

    $eventList->set('field_event_list_keywords', $result);

    return !empty($result);
  }

  /**
   * Migrate places.
   */
  private function migratePlaces(EventList $eventList, array $query): bool {
    if (empty($query['places'])) {
      return FALSE;
    }

    $result = [];
    ['places' => $places] = $query;
    foreach (explode(',', $places) as $place) {
      try {
        $response = $this->client
          ->request('GET', "https://api.hel.fi/linkedevents/v1/place/$place/");

        $item = Utils::jsonDecode($response->getBody()->getContents(), assoc: TRUE);

        $result[] = json_encode(new LinkedEventsItem($item['id'], $item['name']));
      }
      catch (GuzzleException | RuntimeException $e) {
        // Ignore error.
      }
    }

    $eventList->set('field_event_list_place', $result);

    return !empty($result);
  }

  /**
   * Migrate category.
   */
  private function migrateCategory(EventList $eventList, array $query): bool {
    if (empty($query['categories'])) {
      return FALSE;
    }

    $result = array_filter(
      array_map(
        static fn (string $category) => EventCategory::tryFrom($category)?->value,
        explode(',', $query['categories'])
      )
    );

    $eventList->set('field_event_list_category_event', $result);

    return !empty($result);
  }

  /**
   * Migrate query parameters.
   */
  private function migrateCustomQuery(EventList $eventList, array $query): bool {
    $params = [];

    // This is pulled from the previous implementation:
    // https://github.com/City-of-Helsinki/drupal-helfi-platform-config/blob/d1cab545c52d4d69631fb01160e6ab208896da55/modules/helfi_react_search/src/LinkedEvents.php#L320.
    foreach ($query as $key => $param) {
      switch ($key) {
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
          $params['all_ongoing_AND'] = $param;
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

        // Handled separately.
        case 'keyword':
        case 'places':
        case 'categories':
          break;

        default:
          $params[$key] = $param;
          break;
      }
    }

    if ($params) {
      // Free text supports custom query string.
      $eventList->set('field_event_list_free_text', '?' . http_build_query($params));
    }

    return !empty($params);
  }

}
