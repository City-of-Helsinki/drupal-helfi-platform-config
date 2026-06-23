<?php

declare(strict_types=1);

namespace Drupal\helfi_search;

/**
 * Embedding model interface.
 *
 * @todo Better name for this interface would be e.g. EmbeddingApiInterface.
 */
interface EmbeddingsModelInterface {

  /**
   * Get vector embedding for text.
   *
   * @param string $text
   *   Input text.
   * @param \Drupal\helfi_search\EmbeddingModel $model
   *   Model to use.
   *
   * @return float[]
   *   Vector.
   *
   * @throws \Drupal\helfi_search\EmbeddingsModelException
   */
  public function getEmbedding(string $text, EmbeddingModel $model): array;

  /**
   * Get vector embedding for batch.
   *
   * @param string[] $batch
   *   Input batch.
   * @param \Drupal\helfi_search\EmbeddingModel $model
   *   Model to use.
   *
   * @return array<float[]>
   *   Vectors keyed by input keys.
   *
   * @throws \Drupal\helfi_search\EmbeddingsModelException
   */
  public function batchGetEmbedding(array $batch, EmbeddingModel $model): array;

}
