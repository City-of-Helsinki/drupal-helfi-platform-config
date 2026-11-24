<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\SearchAPI;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Utility\Error;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\SearchApiException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Reindexes shared indexes that their main instance cleared or rebuilt.
 *
 * Only the main instance may clear or rebuild a shared index. Both delete the
 * Elasticsearch index and create it again, which drops the documents of every
 * instance and gives the index a new uuid. The other instances compare the
 * uuid with the one they saw last time and mark their items for reindexing
 * when it changed.
 */
final class SharedIndexRebuildDetector {

  /**
   * State key for the last seen uuids, keyed by index id.
   */
  public const string STATE_KEY = 'helfi_platform_config.shared_index_uuids';

  public function __construct(
    private readonly RestrictedSearchIndex $restrictedSearchIndex,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly StateInterface $state,
    #[Autowire(service: 'logger.channel.helfi_platform_config')] private readonly LoggerInterface $logger,
  ) {
  }

  /**
   * Marks items for reindexing in shared indexes that were recreated.
   *
   * The first uuid seen for an index is only stored. An index that does not
   * exist or cannot be read is checked again on the next run.
   */
  public function reindexRecreatedIndexes(): void {
    // helfi_platform_config does not depend on search_api.
    if (!$this->entityTypeManager->hasDefinition('search_api_index')) {
      return;
    }
    $indexIds = $this->restrictedSearchIndex->sharedFromOtherInstances();
    if ($indexIds === []) {
      return;
    }

    /** @var array<string, string> $seen */
    $seen = $this->state->get(self::STATE_KEY, []);
    $indexes = $this->entityTypeManager->getStorage('search_api_index')->loadMultiple($indexIds);
    foreach ($indexes as $indexId => $index) {
      if (!$index instanceof IndexInterface || !$index->status()) {
        continue;
      }
      $backend = $index->getServerInstanceIfAvailable()?->getBackend();
      if (!$backend instanceof RestrictedElasticSearchBackend) {
        continue;
      }
      $uuid = $backend->getElasticIndexUuid($index);
      if ($uuid === NULL) {
        continue;
      }

      $previous = $seen[$indexId] ?? NULL;
      if ($previous !== NULL && $previous !== $uuid) {
        try {
          $index->reindex();
        }
        catch (SearchApiException $e) {
          // Keep the old uuid so the next run tries again.
          Error::logException($this->logger, $e);
          continue;
        }
        $this->logger->info('Shared search index @index was cleared on its main instance, all items were marked for reindexing.', [
          '@index' => $indexId,
        ]);
      }
      $seen[$indexId] = $uuid;
    }
    $this->state->set(self::STATE_KEY, $seen);
  }

}
