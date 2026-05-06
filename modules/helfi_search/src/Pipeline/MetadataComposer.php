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
      $chunk->setMetadata($this->buildMetadata($chunk));
      $chunk->snippet = SnippetRenderer::render($chunk->text);
      $chunk->fragment = $perSectionFragment[$i] ?? NULL;
    }
    return $chunks;
  }

  /**
   * Map chunk indexes to fragments for chunks.
   *
   * The extractor walks the DOM and the chunker walks cleaned Markdown.
   * They can disagree on which headings exist (HtmlCleaner drops wrappers,
   * Markdown may add Markdown syntax), so HeadingFragment::matches() compares
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
      $title = $chunk->context['title'] ?? NULL;
      $level = $chunk->context['level'] ?? NULL;
      $section = $title === NULL ? NULL : [$title, $level, $chunk->parent];

      // A long heading section produces multiple sub-chunks that share the same
      // (title, level, parent). Sub-chunks should all get the same fragment, so
      // we only consume an entry when that triple changes.
      if ($section !== $previousSection) {
        $sectionFragment = NULL;
        if ($title && in_array($level, [2, 3], TRUE)) {
          $matchIndex = array_find_key($headingFragments, static fn (HeadingFragment $entry) => $entry->matches($level, $title));

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
