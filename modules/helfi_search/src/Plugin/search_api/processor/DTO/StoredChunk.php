<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Plugin\search_api\processor\DTO;

/**
 * A chunk read back from the embedding store.
 */
final readonly class StoredChunk {

  /**
   * Constructs a new stored chunk.
   *
   * @param float[] $vector
   *   The embedding.
   * @param string|null $snippet
   *   Human readable excerpt.
   * @param string|null $fragment
   *   Heading anchor.
   */
  public function __construct(
    public array $vector,
    public ?string $snippet = NULL,
    public ?string $fragment = NULL,
  ) {
  }

}
