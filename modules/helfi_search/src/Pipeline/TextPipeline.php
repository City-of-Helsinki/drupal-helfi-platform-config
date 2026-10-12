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
 */
class TextPipeline {

  /**
   * The version of this pipeline's output.
   */
  public const int VERSION = 1;

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
   * @return \Drupal\helfi_search\Pipeline\Document
   *   The document's markdown and its chunks.
   *
   * @throws \Drupal\helfi_search\Pipeline\PipelineException
   *   When a pipeline stage fails.
   */
  public function process(EntityInterface $entity): Document {
    $doc = $this->htmlExtractor->extract($entity);
    $headingFragments = HeadingFragmentExtractor::extract($doc);
    $cleanHtml = $this->htmlCleaner->clean($doc);
    $markdown = MarkdownConverter::convert($cleanHtml);
    $normalized = TextNormalizer::normalize($markdown);
    $chunks = $this->contentChunker->chunk($normalized);

    return new Document(
      $normalized,
      $this->chunkAnnotator->annotate($chunks, $headingFragments),
    );
  }

}
