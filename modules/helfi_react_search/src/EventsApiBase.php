<?php

declare(strict_types = 1);

namespace Drupal\helfi_react_search;

use Drupal\Component\Utility\UrlHelper;
use Drupal\helfi_react_search\Enum\CategoryKeywords;

/**
 * Base class for retrieving events data.
 */
abstract class EventsApiBase {

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
    $parsed = UrlHelper::parse($url);
    $params = [];

    if (!empty($parsed) && isset($parsed['query'])) {
      foreach ($parsed['query'] as $key => $param) {
        switch ($key) {
          case 'categories':
            $params['keyword'] = $this->categoriesToKeywords($param);
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
            foreach ($dateTypes as $dataType) {
              switch ($param) {
                case 'today':
                  $params['end'] = 'today';
                  break;

                case 'tomorrow';
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
    };

    return implode(',', $keywords);
  }

}
