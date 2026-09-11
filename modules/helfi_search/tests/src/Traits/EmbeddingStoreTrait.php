<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_search\Traits;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\helfi_search\DocumentKeyTrait;
use Drupal\helfi_search\DocumentState;
use Drupal\helfi_search\EmbeddingModel;
use Drupal\helfi_search\Plugin\search_api\processor\DTO\StoredChunk;
use Drupal\helfi_search\Vector;

/**
 * Helpers for the embedding store's tables.
 *
 * @phpstan-import-type DocumentKey from \Drupal\helfi_search\DocumentKeyTrait
 */
trait EmbeddingStoreTrait {

  use DocumentKeyTrait;

  /**
   * Writes a document's state.
   *
   * @param \Drupal\Core\Entity\EntityInterface|array $entity
   *   The entity, in the translation to write, or the document's key columns.
   * @param \Drupal\helfi_search\DocumentState $state
   *   The state to write.
   * @param int|null $attempts
   *   The failed attempt count, or NULL to leave it alone.
   * @param int|null $changed
   *   The timestamp to stamp the row with, or NULL for the request time.
   *
   * @phpstan-param \Drupal\Core\Entity\EntityInterface|DocumentKey $entity
   */
  private function setDocumentState(
    EntityInterface|array $entity,
    DocumentState $state,
    ?int $attempts = NULL,
    ?int $changed = NULL,
  ): void {
    if ($entity instanceof EntityInterface) {
      $entity = self::key($entity);
    }

    $fields = [
      'state' => $state->value,
      // Documents are queued oldest first, and the back-off is measured from
      // here, so a seeded row has to look as fresh as a written one.
      'changed' => $changed ?? $this->container->get(TimeInterface::class)->getRequestTime(),
    ];

    if ($attempts !== NULL) {
      $fields['attempts'] = $attempts;
    }

    $this->container->get(Connection::class)
      ->merge(self::DOCUMENT_TABLE)
      ->keys($entity)
      ->fields($fields)
      ->execute();
  }

  /**
   * Asserts the document's row in the store.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity, in the translation to read.
   * @param \Drupal\helfi_search\DocumentState $state
   *   The expected state.
   * @param int|null $attempts
   *   The expected failed attempt count, or NULL to skip the assertion.
   * @param int|null $changed
   *   The expected timestamp of the last state change, or NULL to skip the
   *   assertion.
   */
  private function assertDocumentState(
    EntityInterface $entity,
    DocumentState $state,
    ?int $attempts = NULL,
    ?int $changed = NULL,
  ): void {
    $query = $this->container->get(Connection::class)
      ->select(self::DOCUMENT_TABLE, 'd')
      ->fields('d', ['state', 'attempts', 'changed']);

    $row = self::keyCondition($query, $entity)->execute()->fetchAssoc();

    $this->assertIsArray($row, 'The store has no row for the document.');
    $this->assertSame($state, DocumentState::tryFrom($row['state']));

    if ($attempts !== NULL) {
      $this->assertSame($attempts, (int) $row['attempts']);
    }

    if ($changed !== NULL) {
      $this->assertSame($changed, (int) $row['changed']);
    }
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
   * Writes a document's chunk rows.
   *
   * @phpstan-param \Drupal\Core\Entity\EntityInterface|DocumentKey $entity
   * @phpstan-param \Drupal\helfi_search\Pipeline\Chunk[] $chunks
   * @phpstan-param float[] $vector
   */
  private function fillChunks(
    EntityInterface|array $entity,
    array $chunks,
    EmbeddingModel $model = EmbeddingModel::DEFAULT,
    array $vector = [0.25, 0.5],
  ): void {
    if ($entity instanceof EntityInterface) {
      $entity = self::key($entity);
    }

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
        'entity_type' => $entity['entity_type'],
        'entity_id' => (string) $entity['entity_id'],
        'langcode' => $entity['langcode'],
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
  private function readChunks(EntityInterface $entity, EmbeddingModel $model = EmbeddingModel::DEFAULT): array {
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
   * Reads one column of every chunk row, in delta order.
   *
   * @return array<int, string>
   *   The column's values.
   */
  private function chunkColumn(string $column): array {
    return array_values($this->container->get(Connection::class)
      ->select(self::CHUNK_TABLE, 'c')
      ->fields('c', [$column])
      ->orderBy('delta')
      ->execute()
      ->fetchCol());
  }

  /**
   * Counts every chunk row in the table.
   *
   * @return int
   *   The number of chunk rows.
   */
  private function countChunks(): int {
    $query = $this->container->get(Connection::class)
      ->select(self::CHUNK_TABLE, 'c');
    $query->addExpression('COUNT(*)', 'total');

    return (int) $query->execute()->fetchField();
  }

}
