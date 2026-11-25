<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\SearchAPI;

use Drupal\elasticsearch_connector\Plugin\search_api\backend\ElasticSearchBackend;
use Drupal\elasticsearch_connector\SearchAPI\BackendClient;
use Drupal\helfi_api_base\Azure\PubSub\PubSubManagerInterface;
use Drupal\helfi_api_base\Environment\EnvironmentResolverException;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_platform_config\EventSubscriber\SearchApiReindexSubscriber;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\SearchApiException;
use Drupal\search_api\Utility\Utility;
use Elastic\Elasticsearch\Exception\ElasticsearchException;
use Elastic\Transport\Exception\TransportException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use WebSocket\Exception\Exception as WebSocketException;

/**
 * Lets several instances share one Elasticsearch index.
 *
 * For a shared index:
 *
 * - Clearing deletes only the active instance's documents.
 * - Only the main instance creates, deletes or rebuilds the index. After
 *   creating it, the other instances are asked over PubSub to reindex.
 * - Other instances only add field mappings.
 */
class RestrictedElasticSearchBackend extends ElasticSearchBackend {

  /**
   * The shared index definitions.
   */
  protected RestrictedSearchIndex $restrictedSearchIndex;

  /**
   * The PubSub manager.
   */
  protected PubSubManagerInterface $pubSubManager;

  /**
   * The environment resolver.
   */
  protected EnvironmentResolverInterface $environmentResolver;

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, mixed> $configuration
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->restrictedSearchIndex = $container->get(RestrictedSearchIndex::class);
    $instance->pubSubManager = $container->get(PubSubManagerInterface::class);
    $instance->environmentResolver = $container->get(EnvironmentResolverInterface::class);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function addIndex(IndexInterface $index): void {
    if ($this->isSharedFromOtherInstance($index)) {
      return;
    }
    if (!$this->restrictedSearchIndex->isShared((string) $index->id())) {
      parent::addIndex($index);
      return;
    }

    // The parent returns without changes when the index exists.
    $client = $this->getBackendClient();
    $existed = $client->indexExists($index);
    parent::addIndex($index);
    if (!$existed) {
      $this->announceCreated($index);
    }
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
    if (!$client instanceof BackendClient) {
      if (!$this->isSharedFromOtherInstance($index)) {
        parent::updateIndex($index);
      }
      return;
    }
    if ($this->isSharedFromOtherInstance($index)) {
      // The main instance rebuilds the index. Its PubSub message is missed
      // if no listener runs here at that moment, e.g. during a deployment.
      if ($client->indexChangeRequiresRebuild($index)) {
        $index->reindex();
      }
      if (!$client->indexExists($index)) {
        return;
      }
      // Elasticsearch rejects changes to existing mappings.
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

    if (!$client->indexExists($index)) {
      parent::updateIndex($index);
      $this->announceCreated($index);
      return;
    }

    // The parent would rebuild through Index::clear(), which only deletes
    // this instance's documents.
    if ($client->indexChangeRequiresRebuild($index)) {
      $client->clearIndex($index);
      $this->announceCreated($index);
      return;
    }
    parent::updateIndex($index);
  }

  /**
   * {@inheritdoc}
   *
   * Deletes only this instance's documents from a shared index.
   */
  public function deleteAllIndexItems(IndexInterface $index, $datasource_id = NULL): void {
    $indexId = (string) $index->id();
    $siteField = $this->restrictedSearchIndex->siteField($indexId);
    if ($siteField === NULL) {
      parent::deleteAllIndexItems($index, $datasource_id);
      return;
    }

    $project = $this->activeProject();
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

    // A datasource id means the datasource was removed from the index.
    if ($datasource_id === NULL) {
      $this->explainSharedClear($indexId, $project);
    }
  }

  /**
   * Tells the user that other instances' documents were kept.
   *
   * Drush prints logged warnings.
   */
  private function explainSharedClear(string $indexId, string $project): void {
    $main = (string) $this->restrictedSearchIndex->mainInstance($indexId);
    $args = [
      '@index' => $indexId,
      '@project' => $project,
      '@main' => $main,
    ];

    if (Utility::isRunningInCli()) {
      $message = $main === $project
        ? 'Only the documents of @project were deleted from the shared index @index. To delete the documents of every instance and create the index again, run drush search-api:disable @index && drush search-api:enable @index. The other instances are then asked to reindex.'
        : 'Only the documents of @project were deleted from the shared index @index. To delete the documents of every instance and create the index again, run drush search-api:disable @index && drush search-api:enable @index on @main, its main instance. The other instances are then asked to reindex.';
      $this->getLogger()->warning($message, $args);
      return;
    }
    $message = $main === $project
      ? $this->t('Only the documents of @project were deleted from the shared index @index. To delete the documents of every instance and create the index again, disable and enable the index. The other instances are then asked to reindex.', $args)
      : $this->t('Only the documents of @project were deleted from the shared index @index. To delete the documents of every instance and create the index again, disable and enable the index on @main, its main instance. The other instances are then asked to reindex.', $args);
    $this->messenger()->addWarning($message);
  }

  /**
   * Asks every instance to reindex a newly created shared index.
   */
  private function announceCreated(IndexInterface $index): void {
    $indexId = (string) $index->id();
    $index->reindex();

    $project = $this->activeProject();
    if ($project === NULL) {
      // Only the main instance creates the index, so this is not reached.
      return;
    }
    try {
      $this->pubSubManager->sendMessage(SearchApiReindexSubscriber::message($indexId, $project));
      $this->getLogger()->notice('Asked other instances to reindex the shared index @index.', [
        '@index' => $indexId,
      ]);
    }
    catch (WebSocketException | \JsonException | \LogicException $e) {
      $this->getLogger()->warning('Other instances were not asked to reindex the shared index @index: @message', [
        '@index' => $indexId,
        '@message' => $e->getMessage(),
      ]);
    }
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

  /**
   * The active project name, or NULL when it cannot be resolved.
   */
  private function activeProject(): ?string {
    try {
      return $this->environmentResolver->getActiveProject()->getName();
    }
    catch (EnvironmentResolverException) {
      return NULL;
    }
  }

}
