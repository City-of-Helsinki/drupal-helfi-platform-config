<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Queue;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Statement\FetchAs;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\helfi_search\DocumentKeyTrait;
use Drupal\helfi_search\DocumentState;
use Drupal\helfi_search\EmbeddingApiInterface;
use Drupal\helfi_search\EmbeddingModel;
use Drupal\helfi_search\MissingConfigurationException;
use Drupal\helfi_search\Pipeline\TextPipeline;
use Drupal\helfi_search\Queue\DTO\ChunkDiff;
use Drupal\helfi_search\Queue\DTO\ClaimedDocument;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Plugin\search_api\datasource\ContentEntityTrackingManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Runs the embedding work list.
 *
 * Queue logic:
 * - Something calls markForProssing(), which sets the
 *   document state to pending `pending`. Document row is
 *   created if it is missing.
 * - Cron hook calls claimPending, which selects `pending`
 *   documents and marks them as reserved. The selected
 *   documents are pushed to a Drupal queue.
 * - The queue worker calls process, which marks the
 *   document as `ready` or `skipped` on success and `failed`
 *   or `pending` on failure. Documents are re-tried MAX_ATTEMPTS
 *   times until they are marked `failed`.
 *
 * @phpstan-import-type ChunkRow from \Drupal\helfi_search\Queue\DTO\ChunkDiff
 * @phpstan-import-type DocumentKey from \Drupal\helfi_search\DocumentKeyTrait
 *
 * @see \Drupal\helfi_search\Hook\CronHook
 * @see \Drupal\helfi_search\Plugin\QueueWorker\EmbeddingQueue
 */
class QueueManager {

  use DocumentKeyTrait;

  /**
   * How many failures before the item is considered failed.
   */
  public const int MAX_ATTEMPTS = 3;

  /**
   * The processor plugin that reads the store.
   */
  public const string PROCESSOR_ID = 'helfi_search_embeddings';

  /**
   * How many documents one cron run claims.
   */
  public const int BATCH_SIZE = 100;

  /**
   * How long a document may be in `embedding` state before it is retried.
   */
  public const int STALE_WINDOW = 86400;

  /**
   * How long a document waits after its first failed attempt.
   */
  private const int RETRY_DELAY = 3600;

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
    #[Autowire(service: 'search_api.entity_datasource.tracking_manager')]
    private readonly ContentEntityTrackingManager $trackingManager,
    private readonly TextPipeline $textPipeline,
    private readonly EmbeddingApiInterface $embeddingsModel,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * Marks a document as needing a pipeline run.
   */
  public function markForProcessing(ContentEntityInterface $entity): void {
    $this->database->merge(self::DOCUMENT_TABLE)
      ->keys(self::key($entity))
      ->fields([
        'state' => DocumentState::Pending->value,
        'changed' => $this->time->getRequestTime(),
        'attempts' => 0,
      ])
      ->execute();
  }

  /**
   * Claims documents that need a pipeline run.
   *
   * @return \Drupal\helfi_search\Queue\DTO\ClaimedDocument[]
   *   The claimed documents.
   */
  public function claimPending(): array {
    $now = $this->time->getRequestTime();

    $claimable = $this->database->condition('OR')
      // Pending documents.
      ->condition(
        $this->database->condition('AND')
          ->condition('state', DocumentState::Pending->value)
          ->condition(
            $this->database->condition('OR')
              // Documents that have not been attempted.
              ->condition('attempts', 0)
              // Re-try, wait until the back-off has elapsed.
              ->where('changed <= :now - :delay * POW(2, attempts - 1)', [
                ':now' => $now,
                ':delay' => self::RETRY_DELAY,
              ])
          )
      )
      // Stale documents.
      ->condition(
        $this->database->condition('AND')
          ->condition('state', DocumentState::Embedding->value)
          ->condition('changed', $now - self::STALE_WINDOW, '<')
      );

    $rows = $this->database->select(self::DOCUMENT_TABLE, 'd')
      ->fields('d', ['entity_type', 'entity_id', 'langcode'])
      ->condition($claimable)
      ->orderBy('changed')
      ->range(0, self::BATCH_SIZE)
      ->execute()
      ->fetchAll(FetchAs::Associative);

    if (!$rows) {
      return [];
    }

    $claimed = [];
    $keys = $this->database->condition('OR');

    foreach ($rows as $row) {
      $key = [
        'entity_type' => (string) $row['entity_type'],
        'entity_id' => (string) $row['entity_id'],
        'langcode' => (string) $row['langcode'],
      ];

      $keys->condition(
        self::keyCondition($this->database->condition('AND'), $key)
      );

      $claimed[] = new ClaimedDocument(
        entityType: $key['entity_type'],
        entityId: $key['entity_id'],
        langcode: $key['langcode'],
        changed: $now,
      );
    }

    $this->database->update(self::DOCUMENT_TABLE)
      ->fields([
        'state' => DocumentState::Embedding->value,
        'changed' => $now,
      ])
      ->condition($keys)
      ->execute();

    return $claimed;
  }

  /**
   * Whether a claim on a document is still the current one.
   *
   * @return bool
   *   TRUE when the document still waits for this claim's pipeline run.
   */
  public function isPending(ClaimedDocument $document): bool {
    $query = $this->database->select(self::DOCUMENT_TABLE, 'd')
      ->condition('state', DocumentState::Embedding->value)
      // The queue item is outdated if the changed field does not match.
      ->condition('changed', $document->changed);

    return (bool) self::keyCondition($query, $document->key())
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  /**
   * Runs the pipeline for one claimed document, recording failures.
   *
   * @param \Drupal\helfi_search\Queue\DTO\ClaimedDocument $document
   *   The claimed document.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   The document's entity type does not exist.
   * @throws \Drupal\helfi_search\MissingConfigurationException
   *   Embeddings API is not configured. Every claimed document will fail.
   * @throws \Drupal\helfi_search\Queue\QueueException
   *   The pipeline run failed.
   */
  public function process(ClaimedDocument $document): void {
    $entity = $this->entityTypeManager
      ->getStorage($document->entityType)
      ->load($document->entityId);

    // The entity or its translation may have been deleted since the claim.
    // Delete so that we clean up the database in case entity hooks didn't
    // remove the document correctly. Otherwise, orphan documents are
    // re-processed in every stale window forever.
    if (!$entity instanceof ContentEntityInterface || !$entity->hasTranslation($document->langcode)) {
      $key = self::key($entity);
      $transaction = $this->database->startTransaction();

      try {
        $this->deleteChunks($key);

        self::keyCondition(
          $this->database->delete(self::DOCUMENT_TABLE),
          $key,
        )->execute();
      }
      catch (\Exception $e) {
        $transaction->rollBack();
        throw new QueueException($e->getMessage(), previous: $e);
      }

      return;
    }

    $translation = $entity->getTranslation($document->langcode);

    try {
      $this->run($translation);
    }
    catch (MissingConfigurationException $e) {
      throw $e;
    }
    catch (\Exception $e) {
      $this->recordFailure($translation);
      throw new QueueException($e->getMessage(), previous: $e);
    }
  }

  /**
   * Runs the pipeline for one entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity, in the translation to embed.
   *
   * @throws \Drupal\helfi_search\Pipeline\PipelineException
   *   The document cannot be turned into chunks.
   * @throws \Drupal\helfi_search\EmbeddingApiException
   *   The embeddings API fails the request.
   * @throws \Drupal\helfi_search\MissingConfigurationException
   *   Embeddings API is not configured.
   * @throws \LogicException
   *   Bug in the code.
   * @throws \Exception
   *   Database query fails.
   */
  private function run(ContentEntityInterface $entity): void {
    if ($entity instanceof EntityPublishedInterface && !$entity->isPublished()) {
      $this->setState($entity, DocumentState::Skipped);
      return;
    }

    $chunks = $this->textPipeline->process($entity);

    if (!$chunks) {
      $this->deleteChunks($entity);
      $this->setState($entity, DocumentState::Skipped);
      return;
    }

    $changed = FALSE;

    foreach (EmbeddingModel::ENABLED as $model) {
      // Compare generated chunks with chunks already in the database.
      // We want to re-embed only the content that has changed.
      $diff = $this->diffChunks($entity, $model, $chunks);

      if (!$diff->changed) {
        continue;
      }

      $vectors = $diff->missing
        ? $this->embeddingsModel->batchGetEmbedding($diff->missing, $model)
        : [];

      $this->replaceChunks($entity, $model, $diff->withVectors($vectors));
      $changed = TRUE;
    }

    // Mark the document as ready.
    $this->setState($entity, DocumentState::Ready);

    // Mark entity for search_api indexing when the vectors were updated.
    if ($changed) {
      $this->trackItemUpdated($entity);
    }
  }

  /**
   * Informs `search_api` that the entity needs to be re-indexed.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity, in the translation whose vectors changed.
   */
  private function trackItemUpdated(ContentEntityInterface $entity): void {
    $entityType = $entity->getEntityTypeId();
    $datasourceId = 'entity:' . $entityType;
    $itemId = ContentEntityTrackingManager::formatItemId(
      $entityType,
      (string) $entity->id(),
      $entity->language()->getId(),
    );

    $indexes = array_filter(
      $this->trackingManager->getIndexesForEntity($entity),
      static fn (IndexInterface $index) => $index->isValidProcessor(self::PROCESSOR_ID),
    );

    foreach ($indexes as $index) {
      $itemIds = array_values(ContentEntityTrackingManager::filterValidItemIds($index, $datasourceId, [$itemId]));

      if ($itemIds) {
        $index->trackItemsUpdated($datasourceId, $itemIds);
      }
    }
  }

  /**
   * Compares a document's chunks against the rows already stored.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param \Drupal\helfi_search\EmbeddingModel $model
   *   The embedding model.
   * @param \Drupal\helfi_search\Pipeline\Chunk[] $chunks
   *   The document's chunks.
   *
   * @return \Drupal\helfi_search\Queue\DTO\ChunkDiff
   *   Changes to the chunk table.
   *
   * @throws \Exception
   *   Database query fails.
   */
  private function diffChunks(ContentEntityInterface $entity, EmbeddingModel $model, array $chunks): ChunkDiff {
    $key = self::key($entity);
    $chunks = array_values($chunks);
    $now = $this->time->getRequestTime();

    $query = $this->database->select(self::CHUNK_TABLE, 'c')
      ->fields('c', ['delta', 'content_hash', 'vector', 'snippet', 'fragment'])
      ->condition('model', $model->value);

    $existing = self::keyCondition($query, $entity)
      ->execute()
      ->fetchAllAssoc('delta');

    // If the content hash does not change, re-use vectors we already have.
    $reuse = [];
    foreach ($existing as $row) {
      if ($row->vector !== NULL) {
        $reuse[$row->content_hash] = $row->vector;
      }
    }

    $changed = count($existing) !== count($chunks);
    $missing = [];
    $rows = [];

    foreach ($chunks as $delta => $chunk) {
      $hash = $chunk->contentHash();
      $snippet = $chunk->getTruncatedSnippet();

      $rows[] = $key + [
        'delta' => $delta,
        'model' => $model->value,
        'content_hash' => $hash,
        'vector' => $reuse[$hash] ?? NULL,
        'snippet' => $snippet,
        'fragment' => $chunk->fragment,
        'changed' => $now,
      ];

      if (!isset($reuse[$hash])) {
        $missing[$hash] = (string) $chunk;
      }

      $before = $existing[$delta] ?? NULL;

      // Trigger re-index if the chunk has changed. R-indexing is
      // also needed if e.g., the snippet generation algorithm changes,
      // which does not necessarily affect vectors.
      if (
        !$before ||
        $before->content_hash !== $hash ||
        $before->snippet !== $snippet ||
        $before->fragment !== $chunk->fragment
      ) {
        $changed = TRUE;
      }
    }

    return new ChunkDiff($rows, $missing, $changed);
  }

  /**
   * Swaps a document's chunk rows for one model.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity, in the translation to write.
   * @param \Drupal\helfi_search\EmbeddingModel $model
   *   The embedding model the rows belong to.
   * @param list<ChunkRow> $rows
   *   The rows to write.
   *
   * @throws \Exception
   *   Database query fails.
   */
  private function replaceChunks(ContentEntityInterface $entity, EmbeddingModel $model, array $rows): void {
    $transaction = $this->database->startTransaction();

    try {
      $this->deleteChunks($entity, $model);

      if ($rows) {
        $insert = $this->database->insert(self::CHUNK_TABLE)
          ->fields(array_keys($rows[0]));

        foreach ($rows as $row) {
          $insert->values($row);
        }

        $insert->execute();
      }
    }
    catch (\Exception $e) {
      $transaction->rollBack();
      throw $e;
    }
  }

  /**
   * Deletes a document's chunks, leaving its state row alone.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface|array $document
   *   The entity, in the translation to clear, or its key columns.
   * @param \Drupal\helfi_search\EmbeddingModel|null $model
   *   A single model, or NULL for every model.
   *
   * @phpstan-param \Drupal\Core\Entity\ContentEntityInterface|DocumentKey $document
   */
  private function deleteChunks(ContentEntityInterface|array $document, ?EmbeddingModel $model = NULL): void {
    $query = self::keyCondition(
      $this->database->delete(self::CHUNK_TABLE),
      $document,
    );

    if ($model !== NULL) {
      $query->condition('model', $model->value);
    }

    $query->execute();
  }

  /**
   * Writes the state of a document.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity, in the translation to write.
   * @param \Drupal\helfi_search\DocumentState $state
   *   The state to write.
   */
  private function setState(ContentEntityInterface $entity, DocumentState $state): void {
    $query = $this->database->update(self::DOCUMENT_TABLE)
      ->fields([
        'state' => $state->value,
        'changed' => $this->time->getRequestTime(),
      ]);

    self::keyCondition($query, $entity)
      ->execute();
  }

  /**
   * Counts one failed attempt against a document.
   */
  private function recordFailure(ContentEntityInterface $entity): void {
    $now = $this->time->getRequestTime();

    $query = $this->database->update(self::DOCUMENT_TABLE)
      // Mark the document as failed if the
      // maximum number of attempts is reached.
      ->expression('state', 'CASE WHEN attempts + 1 < :max THEN :pending ELSE :failed END', [
        ':max' => self::MAX_ATTEMPTS,
        ':pending' => DocumentState::Pending->value,
        ':failed' => DocumentState::Failed->value,
      ])
      // Increase the number of attempts.
      ->expression('attempts', 'attempts + 1')
      ->fields([
        'changed' => $now,
      ]);

    self::keyCondition($query, $entity)
      ->execute();
  }

}
