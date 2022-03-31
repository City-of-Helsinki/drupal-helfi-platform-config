<?php

namespace Drupal\helfi_events;

use Drupal\Core\Url;
use GuzzleHttp\Exception\ClientException;

class EventsProxy extends EventsApiBase {
  const API_URL = 'https://tapahtumat-proxy.prod.kuva.hel.ninja/proxy/graphql/';

  /**
   * @return array
   *   Options from API
   */
  public function getNeigborgoodOptions() : array {
    $body = [
      'json' => [
        'operationName' => 'NeighborhoodList',
        'variables' => [],
        'query' => 'query NeighborhoodList {
          neighborhoodList {
            meta {
              count
              __typename
            }
            data {
              id
              name {
                fi
                sv
                en
                __typename
              }
              __typename
            }
            __typename
          }
        }
      '
      ]
    ];

    $json = $this->getContent($body, 'all-neighborhoods');

    if(isset($json->data->neighborhoodList) && $json->data->neighborhoodList->meta->count > 0) {
      $options = [];
      foreach($json->data->neighborhoodList->data as $neighborhood) {
        $options[$neighborhood->id] = $neighborhood->name->fi;
      }
      return $options;
    }

    // Return empty array if no options
    return [];
  }


  /**
   * @return string
   */
  protected function getCacheMaxAge() : string {
    return time() + 60 * 60;
  }

  /**
   * Get cache key for given id.
   * 
   * @param string $id
   *   The id
   * @return string
   *   The cache key
   */
  protected function getCacheKey(string $id) : string {
    $id = preg_replace('/[^a-z0-9_]+/s', '_', $id);

    return sprintf('helfi-events-%s', $id);
  }

  /**
   * Sends HTTP request and returns response data
   * 
   * @param array $body
   *   Body of the request
   * @param string $cacheId
   *   Cache id
   * 
   * @return \stdClass
   *   Data from  the response
   */
  protected function getContent(array $body, string $cacheId = NULL) : \stdClass {
    if ($cacheId && $data = $this->getFromCache($cacheId)) {
      return $data;
    }

    try {
      $response = $this->httpClient->post(self::API_URL, $body);

      if ($response->getStatusCode() === 200 && $response->getBody()) {
        $content = \GuzzleHttp\json_decode($response->getBody());
        $this->setCache($cacheId, $content);

        return $content;
      }
      else {
        //@TODO log undexpected response
      }
    }
    catch(ClientException $e) {
      //@TODO log the error
    }

    // Return empty array if we couldn't read data
    return [];
  }

}
