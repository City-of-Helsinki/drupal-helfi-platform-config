<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Plugin\ExternalEntities\StorageClient;

use Drupal\external_entities\Entity\ExternalEntityInterface;
use Drupal\external_entities\Plugin\ExternalEntities\StorageClient\RestClient;

/**
 * External entity storage client for shared index.
 *
 * @StorageClient(
 *   id = "helfi_shared_index",
 *   label = @Translation("Helfi: Shared index"),
 *   description = @Translation("Retrieves content from shared index")
 * )
 */
final class SharedIndex extends RestClient {

  /**
   * {@inheritdoc}
   */
  public function save(ExternalEntityInterface $entity) : int {
    return 1;
  }

  /**
   * {@inheritdoc}
   */
  public function delete(ExternalEntityInterface $entity) : void {
  }

  /**
   * Maps the given field to something else.
   *
   * @param string $field
   *   The field name to map.
   *
   * @return string
   *   The mapped field.
   */
  protected function getFieldMapping(string $field) : string {
    return $field;
  }

  /**
   * Creates a request against JSON:API.
   *
   * @param array $parameters
   *   The query parameters.
   *
   * @return array
   *   An array of entities.
   */
  protected function request(
    array $parameters,
  ) : array {
    // Return mock data.
    $mock = [
      'hits' => [
        'hits' => [
          [
            '_source' => [
              'uuid' => ['1'],
              'parent_title_fi' => ['Title FI'],
              'parent_title_en' => ['Title EN'],
              'parent_title_sv' => ['Title SV'],
              'parent_id' => ['1'],
              'parent_type' => ['node'],
              'parent_bundle' => ['news_article'],
              'parent_url_fi' => ['https://example.com/fi/foo/1'],
              'parent_url_en' => ['https://example.com/en/1'],
              'parent_url_sv' => ['https://example.com/sv/1'],
              'parent_instance' => ['etusivu'],
            ],
          ],
          [
            '_source' => [
              'uuid' => ['2'],
              'parent_title_fi' => ['Title 2 FI'],
              'parent_title_en' => ['Title 2 EN'],
              'parent_title_sv' => ['Title 2 SV'],
              'parent_id' => ['2'],
              'parent_type' => ['node'],
              'parent_bundle' => ['news_item'],
              'parent_url_fi' => ['https://example.com/fi/foo/2'],
              'parent_url_en' => ['https://example.com/en/2'],
              'parent_url_sv' => ['https://example.com/sv/2'],
              'parent_instance' => ['terveys'],
            ],
          ],
          [
            '_source' => [
              'uuid' => ['3'],
              'parent_title_fi' => ['Title 3 FI'],
              'parent_title_en' => ['Title 3 EN'],
              'parent_title_sv' => ['Title 3 SV'],
              'parent_id' => ['3'],
              'parent_type' => ['node'],
              'parent_bundle' => ['news_article'],
              'parent_url_fi' => ['https://helfi-etusivu.docker.so/fi/uutiset/tulevaisuuden-helsinki-tarvitsee-kansainvalista-osaamista-ja-innovaatioita'],
              'parent_url_en' => ['https://example.com/en/3'],
              'parent_url_sv' => ['https://example.com/sv/3'],
              'parent_instance' => ['etusivu'],
            ],
          ],
        ],
      ],
    ];

    if (!empty($parameters)) {
      $mock['hits']['hits'] = array_filter($mock['hits']['hits'], function ($hit) use ($parameters) {
        return in_array($hit['_source']['uuid'][0], $parameters);
      });
    }

    return $mock['hits']['hits'] ?? [];
  }

  /**
   * Checks whether the API responds or not.
   *
   * @return bool
   *   TRUE if API responds, FALSE if not.
   */
  public function ping() : bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(?array $ids = NULL) : array {
    $data = $this->request($ids ?? []);
    return $data ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function query(
    array $parameters = [],
    array $sorts = [],
    $start = NULL,
    $length = NULL,
  ) : array {
    $uuids = [];
    if (!empty($parameters[0]['field']) && $parameters[0]['field'] === 'uuid') {
      $uuids = $parameters[0]['value'];
    }
    $data = $this->request($uuids);
    return $data ?? [];
  }

}
