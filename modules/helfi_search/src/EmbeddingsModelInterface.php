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
   *
   * @return float[]
   *   Vector.
   *
   * @throws \Drupal\helfi_search\EmbeddingsModelException
   */
  public function getEmbedding(string $text): array;

  /**
   * Get vector embedding for batch.
   *
   * @param string[] $batch
   *   Input batch.
   *
   * @return array<float[]>
   *   Vectors keyed by input keys.
   *
   * @throws \Drupal\helfi_search\EmbeddingsModelException
   */
  public function batchGetEmbedding(array $batch): array;

}
