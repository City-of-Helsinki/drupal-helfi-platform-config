<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\TextConverter;

use Drupal\Core\Entity\EntityInterface;
use League\HTMLToMarkdown\HtmlConverter;

/**
 * Converts entity to markdown by rendering it and converting HTML to markdown.
 */
final readonly class MarkdownTextConverter extends RenderTextConverter {

  /**
   * {@inheritDoc}
   */
  public const array STRATEGIES = [
    Strategy::Markdown,
  ];

  /**
   * {@inheritDoc}
   */
  #[\Override]
  public function convert(EntityInterface $entity, Strategy $strategy): string {
    $document = $this->renderEntity($entity);

    $converter = new HtmlConverter([
      'strip_tags' => TRUE,
      'remove_nodes' => '',
      'header_style' => 'atx',
    ]);

    return trim($converter->convert((string) $document));
  }

  /**
   * {@inheritDoc}
   */
  #[\Override]
  public function chunk(EntityInterface $entity, Strategy $strategy, int $headerLevel = 2, array $context = []): array {
    $markdown = $this->convert($entity, $strategy);
    $chunker = new MarkdownChunker();
    return $chunker->chunk($markdown, $headerLevel, $context);
  }

}
