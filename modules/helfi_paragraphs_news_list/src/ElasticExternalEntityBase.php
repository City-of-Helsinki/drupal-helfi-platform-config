<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_news_list;

use Drupal\Core\Utility\Error;
use Drupal\external_entities\ExternalEntityInterface;
use Drupal\external_entities\StorageClient\ExternalEntityStorageClientBase;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ElasticsearchException;
use Elastic\Transport\Exception\TransportException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class used by taxonomy external entity.
 */
abstract class ElasticExternalEntityBase extends ExternalEntityStorageClientBase {

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
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

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
    $instance->logger = $container->get('logger.factory')->get('helfi_external_entity');

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
      return $this->client->search($parameters)->asArray();
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
      $id = $this->externalEntityType->getFieldMapper()
        ->extractIdFromRawData($hit);
      if (!$id) {
        continue;
      }
      $prepared[$id] = $hit;
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
    $query = [];

    foreach ($parameters as $parameter) {
      ['field' => $field, 'value' => $value, 'operator' => $op] = $parameter;
      $fieldName = $this->getFieldMapping($field);

      if (!$value) {
        continue;
      }
      $callback = match($op) {
        'IN' => function (array $value, string $fieldName) : array {
          $inGroup = [];
          foreach ($value as $v) {
            $inGroup[] = ['term' => [$fieldName => $v]];
          }
          return ['bool' => ['should' => $inGroup]];
        },
        'CONTAINS' => function (string $value, string $fieldName) : array {
          return [
            'regexp' => [
              $fieldName => [
                'value' => $value . '.*',
                'case_insensitive' => TRUE,
              ],
            ],
          ];
        },
        default => function (string|int|null $value, string $fieldName) : array {
          return ['term' => [$fieldName => $value]];
        },
      };
      $query['bool']['must'][] = $callback($value, $fieldName);
    }

    $sortQuery = [];
    foreach ($sorts as $sort) {
      $defaults = ['options' => []];
      ['field' => $field, 'direction' => $direction, 'options' => $options] = $sort + $defaults;
      $fieldName = $this->getFieldMapping($field);

      $sortQuery[$fieldName] = ['order' => strtolower($direction)] + $options;
    }

    return [
      'index' => $this->index,
      'body' => [
        'sort' => $sortQuery,
        'query' => $query,
      ],
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

}
