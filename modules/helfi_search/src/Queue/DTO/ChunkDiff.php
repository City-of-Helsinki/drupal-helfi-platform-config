<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Queue\DTO;

use Drupal\helfi_search\Vector;

/**
 * DTO holding what a chunk rows should become.
 *
 * @phpstan-type ChunkRow array{
 *   entity_type: string,
 *   entity_id: string,
 *   langcode: string,
 *   delta: int,
 *   model: string,
 *   content_hash: string,
 *   vector: string|null,
 *   snippet: string|null,
 *   fragment: string|null,
 *   changed: int
 * }
 */
final readonly class ChunkDiff {

  /**
   * Constructs the diff.
   *
   * @param list<ChunkRow> $rows
   *   The rows the the chunk table should hold.
   * @param array<string, string> $missing
   *   Text that still has to be embedded, keyed by content hash.
   * @param bool $changed
   *   Whether the rows differ from what is stored.
   */
  public function __construct(
    public array $rows,
    public array $missing,
    public bool $changed,
  ) {
    if ($missing && !$changed) {
      throw new \LogicException("Missing vectors should imply changed");
    }
  }

  /**
   * The rows with every vector in place.
   *
   * @param array<string, float[]> $vectorsByHash
   *   Vectors keyed by content hash.
   *
   * @return list<ChunkRow>
   *   The rows, each one carrying a vector.
   *
   * @throws \LogicException
   */
  public function withVectors(array $vectorsByHash): array {
    $rows = $this->rows;

    foreach ($rows as $delta => $row) {
      if ($row['vector'] !== NULL) {
        continue;
      }

      $vector = $vectorsByHash[$row['content_hash']] ?? NULL;

      if ($vector === NULL) {
        throw new \LogicException("Missing vector for chunk");
      }

      $rows[$delta]['vector'] = Vector::pack($vector);
    }

    return $rows;
  }

}
