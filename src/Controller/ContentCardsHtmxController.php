<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Controller;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Renders content cards after the page has loaded.
 */
final class ContentCardsHtmxController implements ContainerInjectionInterface {

  use AutowireTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly RendererInterface $renderer,
  ) {
  }

  /**
   * Builds the teaser cards for a content cards paragraph.
   *
   * @return array<string, mixed>
   *   The card render array, or an empty array when the paragraph does not
   *   apply.
   */
  public function content(ParagraphInterface $paragraph) : array {
    if ($paragraph->bundle() !== 'content_cards' || !$paragraph->hasField('field_content_cards_content_ext')) {
      return [];
    }

    $build = [
      '#cache' => [
        'contexts' => ['languages:language_content'],
      ],
    ];
    $this->renderer->addCacheableDependency($build, $paragraph);
    $parent = $paragraph->getParentEntity();
    if ($parent !== NULL) {
      $this->renderer->addCacheableDependency($build, $parent);
    }

    $build['cards'] = $this->entityTypeManager
      ->getViewBuilder('paragraph')
      ->viewField($paragraph->get('field_content_cards_content_ext'), [
        'type' => 'entity_reference_entity_view',
        'label' => 'hidden',
        'settings' => [
          'view_mode' => 'teaser',
          'link' => FALSE,
        ],
      ]);

    return $build;
  }

}
