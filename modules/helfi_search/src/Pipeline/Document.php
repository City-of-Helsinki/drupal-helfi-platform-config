<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Pipeline;

/**
 * The text pipeline's output for one document.
 */
final readonly class Document {

  /**
   * Constructs a new document.
   *
   * @param string $markdown
   *   Markdown of the whole document.
   * @param \Drupal\helfi_search\Pipeline\Chunk[] $chunks
   *   Chunks.
   */
  public function __construct(
    public string $markdown,
    public array $chunks,
  ) {
  }

}
