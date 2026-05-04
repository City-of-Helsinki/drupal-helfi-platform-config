<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Pipeline;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Entity\EntityInterface;
use League\CommonMark\CommonMarkConverter;
use League\CommonMark\ConverterInterface;

/**
 * Adds metadata to each chunk.
 */
class MetadataComposer {

  private const array SNIPPET_ALLOWED_TAGS = [
    'p',
    'br',
    'ul',
    'ol',
    'li',
  ];

  /**
   * Markdown-to-HTML converter for snippet rendering.
   */
  private readonly ConverterInterface $converter;

  public function __construct() {
    $this->converter = new CommonMarkConverter([
      'html_input' => 'strip',
      'allow_unsafe_links' => FALSE,
    ]);
  }

  /**
   * Populate metadata and snippet on each chunk.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity providing metadata.
   * @param Chunk[] $chunks
   *   Chunks to enrich.
   *
   * @return Chunk[]
   *   The same chunks with metadata and snippet populated.
   */
  public function compose(EntityInterface $entity, array $chunks): array {
    foreach ($chunks as $chunk) {
      $chunk->setMetadata($this->buildMetadata($chunk, $entity));
      $chunk->snippet = $this->renderSnippet($chunk->text);
    }
    return $chunks;
  }

  /**
   * Build metadata labels for an entity.
   *
   * @return string[]
   *   Labeled metadata lines.
   */
  private function buildMetadata(Chunk $chunk, EntityInterface $entity): array {
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

  /**
   * Render a single chunk's markdown body as sanitized HTML.
   */
  private function renderSnippet(string $markdown): string {
    // Strip all markdown headings. The result card already has a title.
    $trimmed = trim((string) preg_replace('/^#{1,6}\s+[^\n]*\R*/um', '', $markdown));

    $html = (string) $this->converter->convert($trimmed);

    return trim(Xss::filter($html, self::SNIPPET_ALLOWED_TAGS));
  }

}
