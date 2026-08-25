<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Pipeline;

use Drupal\Core\Entity\EntityInterface;

/**
 * Converts Drupal entities into text chunks for embedding.
 *
 * Full pipeline consists of
 *  1) HTML extraction,
 *  2) cleaning,
 *  3) Markdown conversion,
 *  4) normalization,
 *  5) chunking,
 *  6) field composition and
 *  7) embedding.
 *
 * Each pipeline stage is a separate service that can be independently replaced
 * This allows experimenting with different strategies for individual stages.
 *
 * - HtmlExtractor: How raw HTML is obtained from an entity.
 * - HtmlCleaner: Which HTML elements are considered non-content noise.
 * - MarkdownConverter: How HTML structure maps to Markdown.
 * - TextNormalizer: What normalization is applied to the text.
 * - ContentChunker: How long content is split into chunks.
 * - ChunkAnnotator: How chunks are annotated.
 */
class TextPipeline {

  public function __construct(
    private readonly HtmlExtractor $htmlExtractor,
    private readonly HtmlCleaner $htmlCleaner,
    private readonly ContentChunker $contentChunker,
    private readonly ChunkAnnotator $chunkAnnotator,
  ) {
  }

  /**
   * Runs text processing pipeline on an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to process.
   *
   * @return Chunk[]
   *   Chunks, ready for embedding.
   *
   * @throws \Drupal\helfi_search\Pipeline\PipelineException
   *   When a pipeline stage fails.
   */
  public function process(EntityInterface $entity): array {
    $doc = $this->htmlExtractor->extract($entity);
    $headingFragments = HeadingFragmentExtractor::extract($doc);
    $cleanHtml = $this->htmlCleaner->clean($doc);
    $markdown = MarkdownConverter::convert($cleanHtml);
    $normalized = TextNormalizer::normalize($markdown);
    $chunks = $this->contentChunker->chunk($normalized);
    return $this->chunkAnnotator->annotate($chunks, $headingFragments);
  }

}
