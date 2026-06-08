<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Pipeline;

/**
 * Annotates chunks with snippet and fragment.
 */
class ChunkAnnotator {

  /**
   * Populate metadata on each chunk.
   *
   * @phpstan-param Chunk[] $chunks
   * @phpstan-param HeadingFragment[] $headingFragments
   *
   * @return Chunk[]
   *   The chunks with snippet and fragment populated.
   */
  public function annotate(array $chunks, array $headingFragments): array {
    foreach ($chunks as $i => $chunk) {
      $chunk->snippet = SnippetRenderer::render($chunk->text);
      $chunk->fragment = $this->buildSectionFragmentMap($chunks, $headingFragments)[$i] ?? NULL;
      $chunk->text_fragment = count($chunks) > 1 ? SnippetRenderer::renderTextFragment($chunk->text) : NULL;
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
   *
   * @todo This is not in use while we POC with search snippet highlighting
   * via text fragment matching. It's likely we still need this with the final
   * approach, as text matching might not be reliable enough for our needs.
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

}
