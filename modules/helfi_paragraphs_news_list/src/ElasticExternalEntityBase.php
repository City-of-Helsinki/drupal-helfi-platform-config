<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_news_list;

use Drupal\Core\Utility\Error;
use Drupal\external_entities\Entity\ExternalEntityInterface;
use Drupal\external_entities\StorageClient\StorageClientBase;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ElasticsearchException;
use Elastic\Transport\Exception\TransportException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class used by taxonomy external entity.
 */
abstract class ElasticExternalEntityBase extends StorageClientBase {

  /**
   * Which endpoint to query.
   *
   * @var string
   */
  protected string $index;

  /**
   * The elastic client.
   *
   * @var \Elastic\Elasticsearch\Client
   */
  protected Client $client;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) : self {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->client = $container->get('helfi_paragraphs_news_list.elastic_client');

    return $instance;
  }

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
    try {
      return $this->client->search($parameters)?->asArray() ?? [];
    }
    catch (ElasticsearchException | TransportException $e) {
      Error::logException($this->logger, $e);
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(?array $ids = NULL) : array {
    $data = $this->request([
      'index' => $this->index,
      'body' => [
        'query' => [
          'bool' => [
            'filter' => [
              'terms' => ['uuid_langcode' => array_values($ids)],
            ],
          ],
        ],
      ],
    ]);

    if (empty($data['hits']['hits'])) {
      return [];
    }

    $prepared = [];
    foreach ($data['hits']['hits'] as $hit) {
      $id = $this->externalEntityType->getFieldMapper('id')
        ->extractFieldValuesFromRawData($hit);
      if (!$id || !isset($id[0]['value'])) {
        continue;
      }
      $prepared[$id[0]['value']] = $hit;
    }
    return $prepared;
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
   * Get callback that builds elasticsearch query fragment for given operator.
   *
   * @param ?string $op
   *   Query operation.
   *
   * @return callable
   *   Handler.
   */
  protected function getOperatorCallback(?string $op): callable {
    return match($op) {
      'IN' => static function (array $value, string $fieldName) : array {
        $inGroup = [];
        foreach ($value as $v) {
          $inGroup[] = ['term' => [$fieldName => $v]];
        }
        return [
          'query' => [
            'bool' => [
              'must' => [
                ['bool' => ['should' => $inGroup]],
              ],
            ],
          ],
        ];
      },
      'CONTAINS' => static function (string $value, string $fieldName) : array {
        return [
          'query' => [
            'bool' => [
              'must' => [
                [
                  'regexp' => [
                    $fieldName => [
                      'value' => $value . '.*',
                      'case_insensitive' => TRUE,
                    ],
                  ],
                ],
              ],
            ],
          ],
        ];
      },
      'GEO_DISTANCE_SORT' => static function (array $value, string $fieldName) : array {
        [$coordinates, $options] = $value;

        return [
          'sort' => [
            [
              '_geo_distance' => [
                $fieldName => $coordinates,
                ...$options,
              ],
            ],
          ],
        ];
      },
      default => static function (string|int|null $value, string $fieldName) : array {
        return [
          'query' => [
            'bool' => [
              'must' => [
                ['term' => [$fieldName => $value]],
              ],
            ],
          ],
        ];
      },
    };
  }

  /**
   * Builds the elastic query for given parameters.
   *
   * @param array $parameters
   *   The parameters.
   * @param array $sorts
   *   The sorts.
   *
   * @return array
   *   The query.
   */
  protected function buildQuery(array $parameters, array $sorts) : array {
    $body = [
      'sort' => [],
      'query' => [],
    ];

    foreach ($parameters as $parameter) {
      ['field' => $field, 'value' => $value, 'operator' => $op] = $parameter;
      $fieldName = $this->getFieldMapping($field);

      if (!$value) {
        continue;
      }

      $callback = $this->getOperatorCallback($op);
      $body = array_merge_recursive($body, $callback($value, $fieldName));
    }

    $sortQuery = [];
    foreach ($sorts as $sort) {
      ['field' => $field, 'direction' => $direction] = $sort;
      $fieldName = $this->getFieldMapping($field);

      $sortQuery[$fieldName] = ['order' => strtolower($direction)];
    }

    $body = array_merge_recursive($body, [
      'sort' => $sortQuery,
    ]);

    return [
      'index' => $this->index,
      'body' => $body,
    ];
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
    $query = $this->buildQuery($parameters, $sorts);

    if (!is_null($start)) {
      $query['from'] = $start;
    }

    if (!is_null($length)) {
      $query['size'] = $length;
    }
    $data = $this->request($query);

    if (empty($data['hits']['hits'])) {
      return [];
    }
    return $data['hits']['hits'];
  }

  /**
   * {@inheritdoc}
   */
  public function querySource(array $parameters = [], array $sorts = [], ?int $start = NULL, ?int $length = NULL): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function transliterateDrupalFilters(array $parameters, array $context = []): array {
    return $this->transliterateDrupalFiltersAlter(
      ['source' => [], 'drupal' => $parameters],
      $parameters,
      $context
    );
  }

}
