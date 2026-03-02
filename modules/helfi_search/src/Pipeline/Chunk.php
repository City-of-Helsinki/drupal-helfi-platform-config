<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Pipeline;

/**
 * A single chunk of text produced by the content chunker.
 *
 * The metadata array is populated by MetadataComposer after chunking.
 * Casting to string produces the final embedding-ready text with metadata
 * labels prepended, separated from the body by "---".
 */
final class Chunk {

  public function __construct(
    public readonly string $text,
    public readonly ?self $parent = NULL,
    public array $context = [],
    public array $metadata = [],
  ) {
  }

  /**
   * Setter for metdata.
   */
  public function setMetadata(array $metadata): self {
    $this->metadata = $metadata;
    return $this;
  }

  /**
   * Render the chunk as embedding-ready text.
   */
  public function __toString(): string {
    if (empty($this->metadata)) {
      return $this->text;
    }

    $parts = $this->metadata;
    $parts[] = '---';
    $parts[] = $this->text;

    return implode("\n", $parts);
  }

}
