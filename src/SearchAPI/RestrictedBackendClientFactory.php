<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\SearchAPI;

use Drupal\elasticsearch_connector\Analyser\AnalyserManager;
use Drupal\elasticsearch_connector\SearchAPI\BackendClientFactory;
use Drupal\elasticsearch_connector\SearchAPI\BackendClientInterface;
use Drupal\elasticsearch_connector\SearchAPI\DeleteParamBuilder;
use Drupal\elasticsearch_connector\SearchAPI\FieldMapper;
use Drupal\elasticsearch_connector\SearchAPI\IndexParamBuilder;
use Drupal\elasticsearch_connector\SearchAPI\Query\QueryParamBuilder;
use Drupal\elasticsearch_connector\SearchAPI\Query\QueryResultParser;
use Drupal\helfi_platform_config\RestrictedSearchIndex;
use Drupal\search_api\Utility\FieldsHelperInterface;
use Elastic\Elasticsearch\Client;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Builds the backend client that guards shared indexes.
 *
 * Replaces elasticsearch_connector.backend_client_factory. The constructor
 * matches that factory and appends the shared-index guard.
 */
class RestrictedBackendClientFactory extends BackendClientFactory {

  public function __construct(
    QueryParamBuilder $queryParamBuilder,
    QueryResultParser $resultParser,
    DeleteParamBuilder $deleteParamBuilder,
    IndexParamBuilder $itemParamBuilder,
    FieldsHelperInterface $fieldsHelper,
    FieldMapper $fieldParamsBuilder,
    LoggerInterface $logger,
    AnalyserManager $analyserManager,
    EventDispatcherInterface $eventDispatcher,
    private readonly RestrictedSearchIndex $restrictedSearchIndex,
  ) {
    parent::__construct(
      $queryParamBuilder,
      $resultParser,
      $deleteParamBuilder,
      $itemParamBuilder,
      $fieldsHelper,
      $fieldParamsBuilder,
      $logger,
      $analyserManager,
      $eventDispatcher,
    );
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, mixed> $settings
   */
  public function create(Client $client, array $settings): BackendClientInterface {
    return new RestrictedBackendClient(
      $this->queryParamBuilder,
      $this->resultParser,
      $this->deleteParamBuilder,
      $this->itemParamBuilder,
      $this->fieldsHelper,
      $this->fieldParamsBuilder,
      $this->logger,
      $client,
      $this->analyserManager,
      $this->eventDispatcher,
      $settings,
      $this->restrictedSearchIndex,
    );
  }

}
