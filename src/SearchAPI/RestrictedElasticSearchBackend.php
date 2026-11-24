<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\SearchAPI;

use Drupal\elasticsearch_connector\Plugin\search_api\backend\ElasticSearchBackend;
use Drupal\elasticsearch_connector\SearchAPI\BackendClient;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\SearchApiException;
use Elastic\Elasticsearch\Exception\ElasticsearchException;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Elastic\Transport\Exception\TransportException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lets several instances share one Elasticsearch index.
 *
 * Replaces the class of the elasticsearch backend plugin, see
 * \Drupal\helfi_platform_config\Hook\SearchApiHooks. For a shared index:
 *
 * - Clearing deletes only the documents of the active instance, found by the
 *   index's site field. This applies to every instance, so a clear never
 *   touches the documents of another instance.
 * - Only the main instance creates, deletes or rebuilds the Elasticsearch
 *   index. A rebuild after a breaking field change drops every instance's
 *   documents; the other instances reindex on their next cron run, see
 *   SharedIndexRebuildDetector.
 * - Other instances may only add field mappings, so their documents are not
 *   mapped dynamically when they deploy a new field before the main instance.
 *
 * Document indexing and deletion work as usual.
 */
class RestrictedElasticSearchBackend extends ElasticSearchBackend {

  /**
   * The shared index definitions.
   */
  protected RestrictedSearchIndex $restrictedSearchIndex;

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, mixed> $configuration
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->restrictedSearchIndex = $container->get(RestrictedSearchIndex::class);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function addIndex(IndexInterface $index): void {
    if ($this->isSharedFromOtherInstance($index)) {
      return;
    }
    parent::addIndex($index);
  }

  /**
   * {@inheritdoc}
   */
  public function removeIndex($index): void {
    if ($this->isSharedFromOtherInstance($index)) {
      return;
    }
    parent::removeIndex($index);
  }

  /**
   * {@inheritdoc}
   */
  public function updateIndex(IndexInterface $index): void {
    $indexId = (string) $index->id();
    if (!$this->restrictedSearchIndex->isShared($indexId)) {
      parent::updateIndex($index);
      return;
    }

    $client = $this->getBackendClient();
    if (!$client instanceof BackendClient || !$client->indexExists($index)) {
      // Only the main instance creates the index.
      if (!$this->isSharedFromOtherInstance($index)) {
        parent::updateIndex($index);
      }
      return;
    }

    if ($this->isSharedFromOtherInstance($index)) {
      // Adding mappings is safe; Elasticsearch rejects changes to existing
      // ones. Settings and rebuilds stay with the main instance.
      try {
        $client->updateFieldMapping($index);
      }
      catch (SearchApiException $e) {
        $this->getLogger()->warning('Field mappings of the shared index @index were not updated: @message', [
          '@index' => $indexId,
          '@message' => $e->getPrevious()?->getMessage() ?? $e->getMessage(),
        ]);
      }
      return;
    }

    // The parent would rebuild through Index::clear(), which only deletes
    // this instance's documents here. Drop and create the index instead.
    if ($client->indexChangeRequiresRebuild($index)) {
      $index->reindex();
      $client->clearIndex($index);
      return;
    }
    parent::updateIndex($index);
  }

  /**
   * Deletes the documents of this instance from a shared index.
   *
   * {@inheritdoc}
   */
  public function deleteAllIndexItems(IndexInterface $index, $datasource_id = NULL): void {
    $indexId = (string) $index->id();
    $siteField = $this->restrictedSearchIndex->siteField($indexId);
    if ($siteField === NULL) {
      parent::deleteAllIndexItems($index, $datasource_id);
      return;
    }

    $project = $this->restrictedSearchIndex->activeProject();
    if ($project === NULL) {
      throw new SearchApiException(sprintf('Cannot clear the shared index %s, because the active project is unknown.', $indexId));
    }
    $client = $this->getBackendClient();
    if (!$client instanceof BackendClient) {
      throw new SearchApiException(sprintf('Cannot clear the shared index %s with this backend client.', $indexId));
    }
    if (!$client->indexExists($index)) {
      return;
    }

    $filters = [['term' => [$siteField => $project]]];
    if ($datasource_id !== NULL) {
      $filters[] = ['term' => ['search_api_datasource' => $datasource_id]];
    }
    try {
      $this->getClient()->deleteByQuery([
        'index' => $client->getIndexId($index),
        'conflicts' => 'proceed',
        'refresh' => TRUE,
        'body' => [
          'query' => ['bool' => ['filter' => $filters]],
        ],
      ]);
    }
    catch (ElasticsearchException | TransportException $e) {
      throw new SearchApiException(sprintf('An error occurred deleting the documents of %s from the shared index %s.', $project, $indexId), 0, $e);
    }
  }

  /**
   * The uuid Elasticsearch gave the index when it was created.
   *
   * Rebuilding an index deletes and creates it again, so the uuid changes, see
   * SharedIndexRebuildDetector.
   *
   * @return string|null
   *   The uuid, or NULL when the index does not exist or cannot be read.
   */
  public function getElasticIndexUuid(IndexInterface $index): ?string {
    $client = $this->getBackendClient();
    if (!$client instanceof BackendClient) {
      return NULL;
    }
    $indexId = $client->getIndexId($index);
    try {
      $response = $this->getClient()->indices()->getSettings([
        'index' => $indexId,
        'name' => 'index.uuid',
      ]);
    }
    catch (ElasticsearchException | TransportException) {
      return NULL;
    }
    // The client returns a promise only in async mode.
    if (!$response instanceof Elasticsearch) {
      return NULL;
    }
    $uuid = $response->asArray()[$indexId]['settings']['index']['uuid'] ?? NULL;
    return is_string($uuid) && $uuid !== '' ? $uuid : NULL;
  }

  /**
   * Whether the index is shared and this is not its main instance.
   *
   * @param \Drupal\search_api\IndexInterface|string $index
   *   The index, or its id when the index was deleted.
   */
  private function isSharedFromOtherInstance(IndexInterface|string $index): bool {
    $indexId = $index instanceof IndexInterface ? $index->id() : $index;
    return is_string($indexId) && $this->restrictedSearchIndex->isSharedFromOtherInstance($indexId);
  }

}
