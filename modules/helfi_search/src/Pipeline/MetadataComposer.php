<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Pipeline;

/**
 * Adds metadata to each chunk.
 */
class MetadataComposer {

  /**
   * Populate metadata and snippet on each chunk.
   *
   * @phpstan-param Chunk[] $chunks
   * @phpstan-param HeadingFragment[] $headingFragments
   *
   * @return Chunk[]
   *   The same chunks with metadata and snippet populated.
   */
  public function compose(array $chunks, array $headingFragments): array {
    $perSectionFragment = $this->buildSectionFragmentMap($chunks, $headingFragments);

    foreach ($chunks as $i => $chunk) {
      $chunk->snippet = SnippetRenderer::render($chunk->text);
      $chunk->fragment = $perSectionFragment[$i] ?? NULL;
      $chunk->setMetadata($this->buildMetadata($chunk));
    }
    return $chunks;
  }

  /**
   * Map chunk indexes to fragments for chunks.
   *
   * The extractor walks the DOM and the chunker walks cleaned Markdown.
   * They can disagree on which headings exist (HtmlCleaner drops wrappers,
   * Markdown may add Markdown syntax), so Heading::matches() compares
   * normalized text. Each fragment is consumed once found so duplicate headings
   * resolve in document order.
   *
   * @phpstan-param \Drupal\helfi_search\Pipeline\Chunk[] $chunks
   * @phpstan-param HeadingFragment[] $headingFragments
   * @phpstan-return array<int, string>
   */
  private function buildSectionFragmentMap(array $chunks, array $headingFragments): array {
    $perChunkIndex = [];
    $sectionFragment = NULL;
    $previousSection = NULL;

    foreach ($chunks as $i => $chunk) {
      $heading = $chunk->heading;
      $section = $heading === NULL ? NULL : [$heading->title, $heading->level, $chunk->parent];

      // A long heading section produces multiple sub-chunks that share the same
      // (title, level, parent). Sub-chunks should all get the same fragment, so
      // we only consume an entry when that triple changes.
      if ($section !== $previousSection) {
        $sectionFragment = NULL;
        if ($heading !== NULL && in_array($heading->level, [2, 3], TRUE)) {
          $matchIndex = array_find_key($headingFragments, static fn (HeadingFragment $entry) => $entry->heading->matches($heading));

          if ($matchIndex !== NULL) {
            $entry = $headingFragments[$matchIndex];
            unset($headingFragments[$matchIndex]);
            if ($entry->fragment !== NULL && $entry->fragment !== '') {
              $sectionFragment = $entry->fragment;
            }
          }
        }
        $previousSection = $section;
      }

      if ($sectionFragment !== NULL) {
        $perChunkIndex[$i] = $sectionFragment;
      }
    }

    return $perChunkIndex;
  }

  /**
   * Build metadata labels for an entity.
   *
   * @return string[]
   *   Labeled metadata lines.
   */
  private function buildMetadata(Chunk $chunk): array {
    $parts = [];

    // We want to add some context from the parent page to each chunk.
    // If chunks are very short, they might match queries that are
    // otherwise unrelated to the page with very high confidence.
    $context = [];
    for ($current = $chunk->parent; $current !== NULL; $current = $current->parent) {

      if ($current->snippet) {
        $context[] = $current->snippet;
      }

      if ($current->heading !== NULL) {
        $context[] = str_repeat('#', $current->heading->level) . ' ' . $current->heading->title;
      }
    }

    if ($context) {
      $parts[] = implode("\n", array_reverse($context));
    }

    return $parts;
  }

}
