<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Pipeline;

use Drupal\Core\Entity\EntityInterface;

/**
 * Adds metadata to each chunk.
 */
class MetadataComposer {

  /**
   * Populate metadata and snippet on each chunk.
   *
   * @phpstan-param Chunk[] $chunks
   *
   * @return Chunk[]
   *   The same chunks with metadata and snippet populated.
   */
  public function compose(EntityInterface $entity, array $chunks, \DOMDocument $doc): array {
    $perSectionFragment = $this->buildSectionFragmentMap($chunks, $doc, $entity);

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
   * @phpstan-param \Drupal\helfi_search\Pipeline\Chunk[] $chunks
   * @phpstan-return array<int, string>
   */
  private function buildSectionFragmentMap(array $chunks, \DOMDocument $doc, EntityInterface $entity): array {
    // Normalize before keying.
    $headingKey = static function (int $level, string $text): string {
      $text = preg_replace('/[*_`]/u', '', $text) ?? $text;
      $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
      return "$level:" . mb_strtolower(trim($text));
    };

    $list = HeadingFragmentExtractor::extract($doc, $entity->language()->getId());
    if ($list === []) {
      return [];
    }

    // Queue fragments per (level, normalized text). The extractor walks the
    // DOM, the chunker walks cleaned Markdown. they can disagree on
    // which headings exist (HtmlCleaner can drop wrappers), so match by
    // heading text and level.
    $queue = [];
    foreach ($list as $entry) {
      $queue[$headingKey($entry['level'], $entry['text'])][] = $entry['fragment'];
    }

    // Walk the chunks in order. A long heading section produces multiple
    // sub-chunks that share the same (title, level, parent). those should
    // all inherit one fragment, so we dequeue only when that triple changes.
    $perChunkIndex = [];
    $sectionFragment = NULL;
    $previousSection = NULL;

    foreach ($chunks as $i => $chunk) {
      $title = $chunk->context['title'] ?? NULL;
      $level = $chunk->context['level'] ?? NULL;
      $section = $title === NULL ? NULL : [$title, $level, $chunk->parent];

      if ($section !== $previousSection) {
        $sectionFragment = NULL;
        if ($title && in_array($level, [2, 3], TRUE)) {
          $key = $headingKey($level, $title);
          if (!empty($queue[$key])) {
            $fragment = array_shift($queue[$key]);
            if ($fragment !== NULL && $fragment !== '') {
              $sectionFragment = $fragment;
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
