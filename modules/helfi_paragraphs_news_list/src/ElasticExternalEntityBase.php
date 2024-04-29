<?php

namespace Drupal\helfi_paragraphs_news_list;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Utility\Error;
use Drupal\external_entities\ExternalEntityInterface;
use Drupal\external_entities\StorageClient\ExternalEntityStorageClientBase;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_api_base\Environment\ServiceEnum;
use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use Elasticsearch\Common\Exceptions\ElasticsearchException;
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
   * @var \Elasticsearch\Client
   */
  protected Client $client;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

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
    $instance->configFactory = $container->get('config.factory');
    /** @var \Drupal\helfi_api_base\Environment\EnvironmentResolver $environmentResolver */
    $environmentResolver = $container->get('helfi_api_base.environment_resolver');

    try {
      $service = $environmentResolver
        ->getEnvironment(Project::ETUSIVU, $environmentResolver->getActiveEnvironmentName())
        ->getService(ServiceEnum::ElasticProxy)
        ->address
        ->getAddress();
      $instance->client = ClientBuilder::create()
        ->setHosts([$service])
        ->build();
    }
    catch (\InvalidArgumentException) {
    }
    $instance->logger = $container->get('logger.factory')->get('helfi_external_entity');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function save(ExternalEntityInterface $entity) : int {
    throw new EntityStorageException('::save() is not supported.');
  }

  /**
   * {@inheritdoc}
   */
  public function delete(ExternalEntityInterface $entity) : void {
    throw new EntityStorageException('::delete() is not supported.');
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
      return $this->client->search($parameters);
    }
    catch (ElasticsearchException $e) {
      Error::logException($this->logger, $e);
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(array $ids = NULL) : array {
    $data = $this->request([
      'index' => $this->index,
      'body' => [
        'query' => [
          'bool' => [
            'filter' => [
              'terms' => ['uuid_langcode' => $ids],
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
      if (strtolower($op) === 'contains') {
        assert(is_string($value));

        $query['regexp'][$fieldName] = [
          'value' => $value . '.*',
          'case_insensitive' => TRUE,
        ];
      }
      else {
        if (!is_array($value)) {
          $value = [$value];
        }
        $query['bool']['filter']['terms'][$fieldName] = $value;
      }
    }

    $sortQuery = [];
    foreach ($sorts as $sort) {
      ['field' => $field, 'direction' => $direction] = $sort;
      $fieldName = $this->getFieldMapping($field);

      $sortQuery[$fieldName] = ['order' => strtolower($direction)];
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
