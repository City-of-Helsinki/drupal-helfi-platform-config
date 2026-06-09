<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Pipeline;

use Drupal\Component\Utility\Unicode;

/**
 * A single chunk of text produced by the content chunker.
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
    // When TRUE the chunk's own snippet and fragment are too short to display;
    // search results fall back to the document's first chunk instead.
    public bool $hidden = FALSE,
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
   * Fold another chunk into this one, returning the merged chunk.
   *
   * The merged chunk keeps this chunk's structural identity (parent, heading,
   * metadata, fragment). $other's content is appended after a blank line.
   */
  public function merge(self $other): self {
    $text = $this->text . "\n\n";

    // Skip the inlined heading when both chunks belong to the same section
    // (sub-chunks of one oversized section share a heading), to avoid
    // repeating the title in the merged body.
    $sameSection = $other->heading !== NULL
      && $this->heading !== NULL
      && $other->heading->matches($this->heading);

    if ($other->heading !== NULL && !$sameSection) {
      $text .= str_repeat('#', $other->heading->level) . ' ' . $other->heading->title . "\n";
    }
    $text .= $other->text;

    return new self(
      text: $text,
      parent: $this->parent,
      heading: $this->heading,
      metadata: $this->metadata,
      fragment: $this->fragment,
    );
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
