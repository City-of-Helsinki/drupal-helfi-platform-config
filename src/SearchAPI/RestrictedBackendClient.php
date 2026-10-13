<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\SearchAPI;

use Drupal\elasticsearch_connector\Analyser\AnalyserManager;
use Drupal\elasticsearch_connector\SearchAPI\BackendClient;
use Drupal\elasticsearch_connector\SearchAPI\DeleteParamBuilder;
use Drupal\elasticsearch_connector\SearchAPI\FieldMapper;
use Drupal\elasticsearch_connector\SearchAPI\IndexParamBuilder;
use Drupal\elasticsearch_connector\SearchAPI\Query\QueryParamBuilder;
use Drupal\elasticsearch_connector\SearchAPI\Query\QueryResultParser;
use Drupal\helfi_platform_config\RestrictedSearchIndex;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Utility\FieldsHelperInterface;
use Elastic\Elasticsearch\Client;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Keeps shared search indexes unchanged on instances other than etusivu.
 *
 * Index deletion, creation, settings, and field mappings return without
 * calling Elasticsearch. Document indexing and deletion stay on the parent
 * client, so content updates continue to be indexed. A Search API clear
 * still marks the tracker for reindexing before it reaches this client.
 */
class RestrictedBackendClient extends BackendClient {

  /**
   * Constructs a client that can skip shared-index changes.
   *
   * @phpstan-param array<string, mixed> $settings
   */
  public function __construct(
    QueryParamBuilder $queryParamBuilder,
    QueryResultParser $resultParser,
    DeleteParamBuilder $deleteParamBuilder,
    IndexParamBuilder $indexParamBuilder,
    FieldsHelperInterface $fieldsHelper,
    FieldMapper $fieldParamsBuilder,
    LoggerInterface $logger,
    Client $client,
    AnalyserManager $analyserManager,
    EventDispatcherInterface $eventDispatcher,
    array $settings,
    protected readonly RestrictedSearchIndex $restrictedSearchIndex,
  ) {
    parent::__construct(
      $queryParamBuilder,
      $resultParser,
      $deleteParamBuilder,
      $indexParamBuilder,
      $fieldsHelper,
      $fieldParamsBuilder,
      $logger,
      $client,
      $analyserManager,
      $eventDispatcher,
      $settings,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function removeIndex($index): void {
    if ($this->skipsSharedIndexChange($index)) {
      return;
    }
    parent::removeIndex($index);
  }

  /**
   * {@inheritdoc}
   */
  public function addIndex(IndexInterface $index): void {
    if ($this->skipsSharedIndexChange($index)) {
      return;
    }
    parent::addIndex($index);
  }

  /**
   * {@inheritdoc}
   */
  public function clearIndex(IndexInterface $index, ?string $datasource_id = NULL): void {
    if ($this->skipsSharedIndexChange($index)) {
      return;
    }
    parent::clearIndex($index, $datasource_id);
  }

  /**
   * Skips settings updates for shared search indexes.
   *
   * {@inheritdoc}
   */
  public function updateSettings(IndexInterface $index): void {
    if ($this->skipsSharedIndexChange($index)) {
      return;
    }
    parent::updateSettings($index);
  }

  /**
   * Skips mapping updates for shared search indexes.
   *
   * {@inheritdoc}
   */
  public function updateFieldMapping(IndexInterface $index): void {
    if ($this->skipsSharedIndexChange($index)) {
      return;
    }
    parent::updateFieldMapping($index);
  }

  /**
   * Whether Elasticsearch must be left unchanged for this index.
   */
  private function skipsSharedIndexChange(mixed $index): bool {
    if ($index instanceof IndexInterface) {
      $indexId = $index->id();
    }
    elseif (is_string($index)) {
      $indexId = $index;
    }
    else {
      return FALSE;
    }

    // Index ID is always a string.
    if (!is_string($indexId)) {
      return FALSE;
    }
    return $this->restrictedSearchIndex->blocksSchemaChange($indexId);
  }

}
