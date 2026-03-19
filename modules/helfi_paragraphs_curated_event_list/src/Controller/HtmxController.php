<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_curated_event_list\Controller;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_paragraphs_curated_event_list\Entity\LinkedEventsEvent;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Controller for Curated event list HTMX response.
 *
 * @see \Drupal\helfi_paragraphs_curated_event_list\Entity\LazyViewBuilder
 * @see \helfi_paragraphs_curated_event_list_paragraph_view()
 */
final readonly class HtmxController implements ContainerInjectionInterface {

  use AutowireTrait;
  public const int MAX_AGE = 3600;

  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
    private RendererInterface $renderer,
  ) {
  }

  /**
   * A HTMX callback for Curated event list.
   *
   * @param \Drupal\paragraphs\Entity\Paragraph $paragraph
   *   The paragraph.
   *
   * @return array
   *   A render array of results.
   */
  public function content(Paragraph $paragraph): array {
    $selections = $paragraph->get('field_events')->referencedEntities();

    $ids = array_map(function (LinkedEventsEvent $event) {
      return $event->id();
    }, $selections);

    $build = [
      '#cache' => [
        'contexts' => ['languages:language_content'],
      ],
      'items' => [],
    ];
    $this->renderer->addCacheableDependency($build, $paragraph);
    $this->renderer->addCacheableDependency($build, $paragraph->getParentEntity());

    $storage = $this->entityTypeManager->getStorage('linkedevents_event');
    /** @var \Drupal\helfi_paragraphs_curated_event_list\Entity\LinkedEventsEvent[] $entities */
    $entities = $storage->loadMultiple($ids);

    foreach ($entities as $entity) {
      $entity->addCacheableDependency($paragraph);

      if ($entity->hasEnded()) {
        continue;
      }
      $build['items'][] = $this->entityTypeManager->getViewBuilder('linkedevents_event')
        ->view($entity);
    }
    // Show maximum of three items.
    $build['items'] = array_slice($build['items'], 0, 3);

    if (empty($build['items'])) {
      $build['message'] = [
        '#markup' => new TranslatableMarkup('Recommended events were not found', options: [
          'context' => 'Curated events list empty message',
        ]),
      ];
      // Cache for an hour by default. This is to ensure the block
      // does not get cached indefinitely in case there are no results.
      // The max-age should bubble up from ::getCacheMaxAge() of each
      // entity.
      // @see \Drupal\helfi_paragraphs_curated_event_list\Entity\LinkedEventsEvent
      $build['#cache']['max-age'] = self::MAX_AGE;
    }

    return $build;
  }

}
