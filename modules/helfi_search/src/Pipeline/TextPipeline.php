<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Pipeline;

use Drupal\Core\Entity\EntityInterface;

/**
 * Converts Drupal entities into text chunks for embedding.
 *
 * Orchestrates the full pipeline: HTML extraction → cleaning → Markdown
 * conversion → normalization → chunking → field composition → embedding.
 *
 * Each pipeline stage is a separate service that can be independently replaced
 * This allows experimenting with different strategies for individual stages
 * without affecting the rest of the pipeline:
 *
 * - HtmlExtractor: How raw HTML is obtained from an entity.
 * - HtmlCleaner: Which HTML elements are considered non-content noise.
 * - MarkdownConverter: How HTML structure maps to Markdown.
 * - TextNormalizer: What normalization is applied to the text.
 * - ContentChunker: How long content is split into chunks.
 * - MetadataComposer: Which entity metadata is prepended to each chunk.
 * - EmbeddingsModelInterface: Which embedding model/provider is used.
 */
class TextPipeline {

  public function __construct(
    private readonly HtmlExtractor $htmlExtractor,
    private readonly HtmlCleaner $htmlCleaner,
    private readonly MarkdownConverter $markdownConverter,
    private readonly TextNormalizer $textNormalizer,
    private readonly ContentChunker $contentChunker,
    private readonly MetadataComposer $metadataComposer,
  ) {
  }

  /**
   * Runs text processing pipeline on an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to process.
   *
   * @return string[]
   *   Chunks, ready for embedding.
   *
   * @throws \Drupal\helfi_search\Pipeline\PipelineException
   *   When a pipeline stage fails.
   */
  private function process(EntityInterface $entity): array {
    $doc = $this->htmlExtractor->extract($entity);
    $cleanHtml = $this->htmlCleaner->clean($doc);
    $markdown = $this->markdownConverter->convert($cleanHtml);
    $normalized = $this->textNormalizer->normalize($markdown);
    $chunks = $this->contentChunker->chunk($normalized);
    return $this->metadataComposer->compose($entity, $chunks);
  }

  /**
   * Extract text chunks from entities without generating embeddings.
   *
   * @param array<string, \Drupal\Core\Entity\EntityInterface> $entities
   *   Entities keyed by an arbitrary string identifier.
   *
   * @return array<string, string[]>
   *   Entity key → chunk texts.
   *
   * @throws \Drupal\helfi_search\Pipeline\PipelineException
   *   When the pipeline fails.
   */
  public function extractChunks(array $entities): array {
    $result = [];

    foreach ($entities as $key => $entity) {
      $chunks = $this->process($entity);

      if (!empty($chunks)) {
        $result[$key] = $chunks;
      }
    }

    return $result;
  }

}
