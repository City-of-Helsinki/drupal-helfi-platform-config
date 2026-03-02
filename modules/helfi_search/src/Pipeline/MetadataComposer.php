<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Pipeline;

use Drupal\Core\Entity\EntityInterface;

/**
 * Composes entity metadata and chunk text into an embedding-ready string.
 *
 * Produces labeled output in the form:
 *
 * @code
 *   {ancestor headings}
 *   ---
 *   {chunk_text}
 * @endcode
 */
readonly class MetadataComposer {

  /**
   * Add entity metadata to each chunk.
   *
   * Populates the metadata array on each Chunk so that casting the chunk
   * to string produces the final embedding-ready text.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity providing metadata.
   * @param Chunk[] $chunks
   *   Chunks to enrich with metadata.
   *
   * @return string[]
   *   The same chunks with metadata populated.
   */
  public function compose(EntityInterface $entity, array $chunks): array {
    return array_map(
      fn (Chunk $chunk) => (string) $chunk->setMetadata($this->buildMetadata($chunk, $entity)),
      $chunks
    );
  }

  /**
   * Build metadata labels for an entity.
   *
   * @return string[]
   *   Labeled metadata lines.
   */
  private function buildMetadata(Chunk $chunk, EntityInterface $entity): array {
    $parts = [];

    $headings = $this->getAncestorHeadings($chunk);
    if ($headings) {
      $parts[] = implode(' > ', $headings);
    }

    return $parts;
  }

  /**
   * Collect heading titles from chunk's parent chain.
   *
   * @return string[]
   *   Ancestor titles from outermost to innermost, including the chunk's own.
   */
  private function getAncestorHeadings(Chunk $chunk): array {
    $titles = [];
    $current = $chunk->parent;
    while ($current !== NULL) {
      $title = $current->context['title'] ?? NULL;
      if ($title !== NULL) {
        array_unshift($titles, $title);
      }
      $current = $current->parent;
    }
    return $titles;
  }

}
