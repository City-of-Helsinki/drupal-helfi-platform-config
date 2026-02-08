<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\TextConverter;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Render\RendererInterface;
use League\HTMLToMarkdown\HtmlConverter;

/**
 * Coverts entity to text by rendering it and then stripping html tags.
 */
readonly class RenderTextConverter implements TextConverterInterface {

  /**
   * The Drupal view mode that is used for text conversion.
   */
  public const string TEXT_CONVERTER_VIEW_MODE = 'text_converter';

  /**
   * Strategies that this converter supports.
   */
  public const array STRATEGIES = [
    Strategy::Default,
    Strategy::Markdown,
  ];

  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
    private EntityDisplayRepositoryInterface $displayRepository,
    private RendererInterface $renderer,
    private ModuleHandlerInterface $moduleHandler,
  ) {
  }

  /**
   * {@inheritDoc}
   */
  public function applies(EntityInterface $entity, Strategy $strategy): bool {
    if (!in_array($strategy, static::STRATEGIES)) {
      return FALSE;
    }

    // This converter matches entities that have
    // text_converter display enabled.
    $viewModes = $this
      ->displayRepository
      ->getViewModeOptionsByBundle($entity->getEntityTypeId(), $entity->bundle());

    return array_key_exists(self::TEXT_CONVERTER_VIEW_MODE, $viewModes);
  }

  /**
   * {@inheritDoc}
   */
  public function convert(EntityInterface $entity, Strategy $strategy): string {
    $document = $this->renderEntity($entity);

    if ($strategy === Strategy::Markdown) {
      $converter = new HtmlConverter([
        'strip_tags' => TRUE,
        'remove_nodes' => '',
        'header_style' => 'atx',
      ]);

      return $this->cleanNewlines($converter->convert((string) $document));
    }

    if ($strategy === Strategy::Default) {
      // Strip HTML tags, entities and excessive newlines.
      return $this->cleanNewlines(html_entity_decode(strip_tags((string) $document)));
    }

    throw new \InvalidArgumentException("Unknown strategy");
  }

  /**
   * {@inheritDoc}
   */
  public function chunk(EntityInterface $entity, Strategy $strategy, int $headerLevel = 2, array $context = []): array {
    $text = $this->convert($entity, $strategy);
    return [$text];
  }

  /**
   * Clean up excessive newlines.
   */
  protected function cleanNewlines(string $html): string {
    return trim(preg_replace("/\n\s*\n\s*/u", "\n\n", $html));
  }

  /**
   * Render entity as HTML.
   */
  protected function renderEntity(EntityInterface $entity): Document {
    $builder = $this
      ->entityTypeManager
      ->getViewBuilder($entity->getEntityTypeId());

    $view = $builder->view($entity, self::TEXT_CONVERTER_VIEW_MODE, $entity->language()->getId());
    $markup = $this->renderer
      ->renderInIsolation($view);

    $document = new Document($markup);

    // Allow markup postprocessing.
    $this->moduleHandler->alter(
      ['text_conversion', $entity->getEntityTypeId() . '_text_conversion'],
      $document,
      $entity
    );

    return $document;
  }

}
