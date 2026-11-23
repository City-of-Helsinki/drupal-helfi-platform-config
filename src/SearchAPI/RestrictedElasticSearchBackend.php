<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\SearchAPI;

use Drupal\elasticsearch_connector\Plugin\search_api\backend\ElasticSearchBackend;
use Drupal\elasticsearch_connector\SearchAPI\BackendClient;
use Drupal\search_api\IndexInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Keeps each shared search index unchanged outside its main instance.
 *
 * Replaces the class of the elasticsearch backend plugin, see
 * \Drupal\helfi_platform_config\Hook\SearchApiHooks. Index creation,
 * deletion, updates and clearing return without calling Elasticsearch.
 * Document indexing and deletion are not guarded, so content updates continue
 * to be indexed. A Search API clear still marks the tracker for reindexing
 * before it reaches the backend.
 */
class RestrictedElasticSearchBackend extends ElasticSearchBackend {

  /**
   * The shared-index guard.
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
    if ($this->skipsSharedIndexChange($index)) {
      return;
    }
    parent::addIndex($index);
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
  public function updateIndex(IndexInterface $index): void {
    if ($this->skipsSharedIndexChange($index)) {
      // After a breaking field change the main instance rebuilds the shared
      // Elasticsearch index, which drops this instance's documents too, so
      // mark them for reindexing here.
      $client = $this->getBackendClient();
      if ($client instanceof BackendClient && $client->indexChangeRequiresRebuild($index)) {
        $index->reindex();
      }
      return;
    }
    parent::updateIndex($index);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAllIndexItems(IndexInterface $index, $datasource_id = NULL): void {
    if ($this->skipsSharedIndexChange($index)) {
      return;
    }
    parent::deleteAllIndexItems($index, $datasource_id);
  }

  /**
   * Whether Elasticsearch must be left unchanged for this index.
   *
   * @param \Drupal\search_api\IndexInterface|string $index
   *   The index, or its id when the index was deleted.
   */
  private function skipsSharedIndexChange(IndexInterface|string $index): bool {
    $indexId = $index instanceof IndexInterface ? $index->id() : $index;
    if (!is_string($indexId)) {
      return FALSE;
    }
    $action = $this->restrictedSearchIndex->actionFor([$indexId])[$indexId]
      ?? RestrictedClearAction::Allow;
    return $action === RestrictedClearAction::Block;
  }

}
