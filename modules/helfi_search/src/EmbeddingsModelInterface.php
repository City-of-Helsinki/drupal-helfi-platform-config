<?php

declare(strict_types=1);

namespace Drupal\helfi_search;

/**
 * Embedding model interface.
 */
interface EmbeddingsModelInterface {

  /**
   * Get vector embedding for text.
   *
   * @param string $text
   *   Input text.
   * @param string $model
   *   Model name.
   *
   * @return float[]
   *   Vector.
   *
   * @throws \Drupal\helfi_search\EmbeddingsModelException
   */
  public function getEmbedding(string $text, string $model): array;

  /**
   * Get vector embedding for batch.
   *
   * @param string[] $batch
   *   Input batch.
   * @param string $model
   *   Model name.
   *
   * @return array<float[]>
   *   Vectors keyed by input keys.
   *
   * @throws \Drupal\helfi_search\EmbeddingsModelException
   */
  public function batchGetEmbedding(array $batch, string $model): array;

}
