<?php

namespace Drupal\helfi_paragraphs_news_list;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Utility\Error;
use Drupal\elasticsearch_connector\Exception\ElasticSearchConnectorException;
use Drupal\external_entities\ExternalEntityInterface;
use Drupal\external_entities\StorageClient\ExternalEntityStorageClientBase;
use Drupal\helfi_api_base\Environment\Environment;
use Drupal\helfi_api_base\Environment\Project;
use Elasticsearch\ClientBuilder;
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
   * The active endpoint environment.
   *
   * @var \Drupal\helfi_api_base\Environment\Environment|null
   */
  private ?Environment $environment = NULL;

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
      $instance->environment = $environmentResolver
        ->getEnvironment(Project::ETUSIVU, $environmentResolver->getActiveEnvironmentName());
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
    if (!$this->environment) {
      return [];
    }
    try {
      $client = ClientBuilder::create()
        ->setHosts([$this->environment->getService('elastic-proxy')->address->getAddress()])
        ->build();

      return $client->search($parameters);
    }
    catch (ElasticSearchConnectorException $e) {
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
      'from' => $start ?? 0,
      'size' => $length ?? 10,
      'body' => [
        'query' => [
          'bool' => [
            'filter' => [
              'terms' => ['_id' => $ids],
            ],
          ],
        ],
      ],
    ]);
    if (empty($data['hits']['hits'])) {
      return [];
    }
    return $data['hits']['hits'];
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
    $query = [];

    foreach ($parameters as $parameter) {
      ['field' => $field, 'value' => $value] = $parameter;

      if (!$value) {
        continue;
      }
      $query[$field] = ['value' => $value];
    }

    $sortQuery = [];
    foreach ($sorts as $sort) {
      ['field' => $field, 'direction' => $direction] = $sort;

      $sortQuery[$field] = ['order' => strtolower($direction)];
    }

    $query = [
      'index' => $this->index,
      'from' => $start ?? 0,
      'size' => $length ?? 10,
      'body' => [
        'sort' => $sortQuery,
        'query' => [
          'constant_score' => ['filter' => ['term' => $query]],
        ],
      ],
    ];
    $data = $this->request($query);

    if (empty($data['hits']['hits'])) {
      return [];
    }
    return $data['hits']['hits'];
  }

}
