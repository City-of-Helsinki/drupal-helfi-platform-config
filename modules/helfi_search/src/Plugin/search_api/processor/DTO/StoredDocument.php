<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Plugin\search_api\processor\DTO;

use Drupal\helfi_search\EmbeddingModel;

/**
 * A document read back from the embedding store.
 */
final readonly class StoredDocument {

  /**
   * Constructs a new stored document.
   *
   * @param string|null $markdown
   *   The document markdown, or NULL if the document is not stored.
   * @param array<string, \Drupal\helfi_search\Plugin\search_api\processor\DTO\StoredChunk[]> $chunks
   *   The document's chunks, keyed by embedding model.
   */
  public function __construct(
    public ?string $markdown = NULL,
    public array $chunks = [],
  ) {
  }

  /**
   * Gets the chunks embedded with the given model.
   *
   * @return \Drupal\helfi_search\Plugin\search_api\processor\DTO\StoredChunk[]
   *   The chunks.
   */
  public function getChunks(EmbeddingModel $model): array {
    return $this->chunks[$model->value] ?? [];
  }

  /**
   * Returns false if the document has stored no chunks.
   */
  public function hasChunks(): bool {
    return array_any(EmbeddingModel::ENABLED, fn(EmbeddingModel $model) => !empty($this->chunks[$model->value]));
  }

}
