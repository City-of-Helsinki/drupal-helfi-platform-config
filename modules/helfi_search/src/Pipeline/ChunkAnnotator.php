<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Pipeline;

/**
 * Annotates chunks with snippet and fragment, and merges very short chunks.
 */
class ChunkAnnotator {

  /**
   * Body length below which a chunk is considered too short to stand alone.
   */
  private const int SHORT_CHUNK_LENGTH = 200;

  /**
   * Upper bound on how many source chunks fold into one merged chunk.
   */
  private const int MAX_MERGE_GROUP = 4;

  /**
   * Merge very short chunks and populate snippet + fragment on each.
   *
   * @phpstan-param Chunk[] $chunks
   * @phpstan-param HeadingFragment[] $headingFragments
   *
   * @return Chunk[]
   *   The chunks (some merged) with snippet and fragment populated.
   */
  public function annotate(array $chunks, array $headingFragments): array {
    $chunks = $this->mergeShortChunks($chunks);
    $perSectionFragment = $this->buildSectionFragmentMap($chunks, $headingFragments);

    foreach ($chunks as $i => $chunk) {
      $chunk->snippet = SnippetRenderer::render($chunk->text);
      $chunk->fragment = $perSectionFragment[$i] ?? NULL;
    }
    return $chunks;
  }

  /**
   * Combine runs of very short consecutive chunks into larger ones.
   *
   * Very short bodies match queries with too little signal. We fold up to
   * MAX_MERGE_GROUP consecutive short chunks into the first, inlining each
   * subsequent chunk's heading as Markdown in the body so the embedding still
   * sees the section labels.
   *
   * @phpstan-param Chunk[] $chunks
   * @phpstan-return Chunk[]
   */
  private function mergeShortChunks(array $chunks): array {
    $result = [];
    $accumulator = NULL;
    $groupSize = 0;

    foreach ($chunks as $chunk) {
      if ($accumulator === NULL) {
        $accumulator = $chunk;
        $groupSize = 1;
        continue;
      }

      $bothShort = mb_strlen($accumulator->text) < self::SHORT_CHUNK_LENGTH
        && mb_strlen($chunk->text) < self::SHORT_CHUNK_LENGTH;

      if ($bothShort && $groupSize < self::MAX_MERGE_GROUP) {
        $accumulator = $this->mergePair($accumulator, $chunk);
        $groupSize++;
      }
      else {
        $result[] = $accumulator;
        $accumulator = $chunk;
        $groupSize = 1;
      }
    }

    if ($accumulator !== NULL) {
      $result[] = $accumulator;
    }

    return $result;
  }

  /**
   * Fold $b into $a, inlining $b's heading as Markdown when it differs.
   */
  private function mergePair(Chunk $a, Chunk $b): Chunk {
    $text = $a->text . "\n\n";

    // Skip the inlined heading when both chunks belong to the same section
    // (sub-chunks of one oversized section share a heading reference), to
    // avoid repeating the title in the merged body.
    $sameSection = $b->heading !== NULL
      && $a->heading !== NULL
      && $b->heading->matches($a->heading);

    if ($b->heading !== NULL && !$sameSection) {
      $text .= str_repeat('#', $b->heading->level) . ' ' . $b->heading->title . "\n";
    }
    $text .= $b->text;

    return new Chunk($text, $a->parent, $a->heading);
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

}
