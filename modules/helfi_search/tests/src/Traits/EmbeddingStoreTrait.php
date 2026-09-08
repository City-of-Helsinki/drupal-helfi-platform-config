<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_search\Traits;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\helfi_search\DocumentKeyTrait;
use Drupal\helfi_search\DocumentState;
use Drupal\helfi_search\EmbeddingModel;
use Drupal\helfi_search\Plugin\search_api\processor\DTO\StoredChunk;
use Drupal\helfi_search\Vector;

/**
 * Provides a trait for seeding and reading the embedding store's tables.
 *
 * @see \Drupal\helfi_search\Queue\QueueManager
 */
trait EmbeddingStoreTrait {

  use DocumentKeyTrait;

  /**
   * Writes a document's state.
   */
  private function setDocumentState(
    EntityInterface $entity,
    DocumentState $state,
    ?int $attempts = NULL,
  ): void {
    $fields = [
      'state' => $state->value,
      // Documents are queued oldest first, and the back-off is measured from
      // here, so a seeded row has to look as fresh as a written one.
      'changed' => $this->container->get('datetime.time')->getRequestTime(),
    ];

    if ($attempts !== NULL) {
      $fields['attempts'] = $attempts;
    }

    $this->container->get(Connection::class)
      ->merge(self::DOCUMENT_TABLE)
      ->keys(self::key($entity))
      ->fields($fields)
      ->execute();
  }

  /**
   * Reads a document's state.
   *
   * @return \Drupal\helfi_search\DocumentState|null
   *   The state, or NULL when the document is unknown to the store.
   */
  private function getState(EntityInterface $entity): ?DocumentState {
    $state = $this->documentField($entity, 'state');
    return is_string($state) ? DocumentState::tryFrom($state) : NULL;
  }

  /**
   * Reads a document's failed attempt count.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity, in the translation to read.
   *
   * @return int
   *   The number of consecutive failed attempts.
   */
  private function getAttempts(EntityInterface $entity): int {
    return (int) $this->documentField($entity, 'attempts');
  }

  /**
   * Counts documents per state.
   *
   * @return array<string, int>
   *   Document counts, keyed by state.
   */
  private function countByState(): array {
    $query = $this->container->get(Connection::class)
      ->select(self::DOCUMENT_TABLE, 'd');
    $query->addField('d', 'state');
    $query->addExpression('COUNT(*)', 'total');
    $query->groupBy('d.state');

    return array_map(
      static fn ($total) => (int) $total,
      $query->execute()->fetchAllKeyed(),
    );
  }

  /**
   * Reads one column of a document's row.
   *
   * @return mixed
   *   The column's value, or FALSE when the store has no such row.
   */
  private function documentField(EntityInterface $entity, string $column): mixed {
    $query = $this->container->get(Connection::class)
      ->select(self::DOCUMENT_TABLE, 'd')
      ->fields('d', [$column]);

    return self::keyCondition($query, $entity)
      ->execute()
      ->fetchField();
  }

  /**
   * Writes a document's chunk rows.
   *
   * @phpstan-param \Drupal\helfi_search\Pipeline\Chunk[] $chunks
   * @phpstan-param float[] $vector
   */
  private function fillChunks(
    EntityInterface $entity,
    EmbeddingModel $model,
    array $chunks,
    array $vector = [0.25, 0.5],
  ): void {
    $insert = $this->container->get(Connection::class)
      ->insert(self::CHUNK_TABLE)
      ->fields([
        'entity_type',
        'entity_id',
        'langcode',
        'delta',
        'model',
        'content_hash',
        'vector',
        'snippet',
        'fragment',
        'changed',
      ]);

    foreach (array_values($chunks) as $delta => $chunk) {
      $insert->values([
        'entity_type' => $entity->getEntityTypeId(),
        'entity_id' => (string) $entity->id(),
        'langcode' => $entity->language()->getId(),
        'delta' => $delta,
        'model' => $model->value,
        'content_hash' => $chunk->contentHash(),
        'vector' => Vector::pack($vector),
        'snippet' => $chunk->snippet,
        'fragment' => $chunk->fragment,
        'changed' => 0,
      ]);
    }

    $insert->execute();
  }

  /**
   * Reads a document's chunk rows back.
   *
   * @return \Drupal\helfi_search\Plugin\search_api\processor\DTO\StoredChunk[]
   *   The document's chunks.
   */
  private function readChunks(EntityInterface $entity, EmbeddingModel $model): array {
    $query = $this->container->get(Connection::class)
      ->select(self::CHUNK_TABLE, 'c')
      ->fields('c', ['vector', 'snippet', 'fragment']);

    $rows = self::keyCondition($query, $entity)
      ->condition('model', $model->value)
      ->orderBy('delta')
      ->execute();

    $chunks = [];

    foreach ($rows as $row) {
      $chunks[] = new StoredChunk(
        Vector::unpack($row->vector),
        $row->snippet,
        $row->fragment,
      );
    }

    return $chunks;
  }

  /**
   * Counts every chunk row in the table.
   *
   * @return int
   *   The number of chunk rows.
   */
  private function countChunks(): int {
    return $this->chunkExpression('COUNT(*)');
  }

  /**
   * Runs one aggregate over the whole chunk table.
   *
   * @param string $expression
   *   The aggregate expression.
   *
   * @return int
   *   The aggregate's value.
   */
  private function chunkExpression(string $expression): int {
    $query = $this->container->get(Connection::class)
      ->select(self::CHUNK_TABLE, 'c');
    $query->addExpression($expression, 'value');

    return (int) $query->execute()->fetchField();
  }

}
