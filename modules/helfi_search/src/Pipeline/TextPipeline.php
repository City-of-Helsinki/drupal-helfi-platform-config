<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Pipeline;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Utility\Error;
use Drupal\helfi_search\EmbeddingsModelException;
use Drupal\helfi_search\EmbeddingsModelInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * Converts Drupal entities into vector embeddings.
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
class TextPipeline implements LoggerAwareInterface {

  use LoggerAwareTrait;

  public function __construct(
    private readonly HtmlExtractor $htmlExtractor,
    private readonly HtmlCleaner $htmlCleaner,
    private readonly MarkdownConverter $markdownConverter,
    private readonly TextNormalizer $textNormalizer,
    private readonly ContentChunker $contentChunker,
    private readonly MetadataComposer $metadataComposer,
    private readonly EmbeddingsModelInterface $embeddingsModel,
  ) {
  }

  /**
   * Runs text processing pipeline on an entity.
   *
   * The entity is converted into text chunks.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to process.
   *
   * @return string[]
   *   Chunks, ready for embedding.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *   When HTML extraction fails.
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
   * Process entities through the full pipeline and return embeddings.
   *
   * Entities that fail text processing are logged and skipped. If no entities
   * produce text, or the embedding API call fails, an empty array is returned.
   *
   * @param array<string, \Drupal\Core\Entity\EntityInterface> $entities
   *   Entities keyed by an arbitrary string identifier.
   *
   * @return array<string, array{'vector': array<float[]>, 'chunk': \Drupal\helfi_search\Pipeline\Chunk}>
   *   Embedding vectors keyed by the same identifiers. Each value is an array
   *   of vectors (one per chunk). Keys with no results are omitted.
   */
  public function processEntities(array $entities): array {
    $textsForEmbedding = [];
    $entityChunkMap = [];

    foreach ($entities as $key => $entity) {
      try {
        $chunks = $this->process($entity);

        foreach ($chunks as $chunkIndex => $chunk) {
          $flatKey = $key . ':' . $chunkIndex;
          $textsForEmbedding[$flatKey] = $chunk;
          $entityChunkMap[$key][] = $flatKey;
        }
      }
      catch (\Throwable $e) {
        if ($this->logger) {
          Error::logException($this->logger, $e);
        }
      }
    }

    if (empty($textsForEmbedding)) {
      return [];
    }

    try {
      $embeddings = $this->embeddingsModel->batchGetEmbedding($textsForEmbedding);
    }
    catch (EmbeddingsModelException $e) {
      if ($this->logger) {
        Error::logException($this->logger, $e);
      }

      return [];
    }

    $results = [];
    foreach ($entityChunkMap as $entityKey => $chunkKeys) {
      foreach ($chunkKeys as $chunkKey) {
        if (isset($embeddings[$chunkKey])) {
          $results[$entityKey][] = [
            'vector' => $embeddings[$chunkKey],
            'content' => $textsForEmbedding[$chunkKey],
          ];
        }
      }
    }

    return $results;
  }

}
