<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Pipeline;

use Drupal\Component\Utility\Unicode;

/**
 * A single chunk of text produced by the content chunker.
 *
 * The metadata array is populated by MetadataComposer after chunking.
 * Casting to string produces the final embedding-ready text with metadata
 * labels prepended, separated from the body by "---".
 */
final class Chunk {

  /**
   * Constructs a new chunk.
   *
   * @phpstan-param array<string, string> $metadata
   */
  public function __construct(
    public readonly string $text,
    public readonly ?self $parent = NULL,
    public readonly ?Heading $heading = NULL,
    public array $metadata = [],
    public ?string $snippet = NULL,
    public ?string $fragment = NULL,
  ) {
  }

  /**
   * Setter for metdata.
   *
   * @phpstan-param array<string, string> $metadata
   */
  public function setMetadata(array $metadata): self {
    $this->metadata = $metadata;
    return $this;
  }

  /**
   * Render the chunk as embedding-ready text.
   */
  public function __toString(): string {
    $parts = [];

    // We want to add some context from the parent page to each chunk.
    // If chunks are very short, they might match queries that are
    // otherwise unrelated to the page with very high confidence.
    $context = [];
    for ($current = $this->parent; $current !== NULL; $current = $current->parent) {

      if ($current->snippet) {
        $context[] = Unicode::truncate($current->snippet, 300, TRUE);
      }

      if ($current->heading !== NULL) {
        $context[] = str_repeat('#', $current->heading->level) . ' ' . $current->heading->title;
      }
    }

    if ($context) {
      $parts[] = implode("\n", array_reverse($context));
    }

    $parts = array_merge($parts, $this->metadata);

    if (!empty($this->heading)) {
      $parts[] = str_repeat('#', $this->heading->level) . ' ' . $this->heading->title;
    }

    $parts[] = $this->text;

    return implode("\n", $parts);
  }

}
