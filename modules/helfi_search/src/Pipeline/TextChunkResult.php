<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Pipeline;

/**
 * Result of text chunk extraction from entities.
 */
final readonly class TextChunkResult {

  /**
   * Constructs a TextChunkResult.
   *
   * @param array<string, string> $textsForEmbedding
   *   Flat key → chunk text.
   * @param array<string, string[]> $entityChunkMap
   *   Entity key → flat keys.
   */
  public function __construct(
    public array $textsForEmbedding,
    public array $entityChunkMap,
  ) {
  }

}
