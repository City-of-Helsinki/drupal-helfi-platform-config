<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_curated_event_list\Controller;

use Drupal\Component\Datetime\TimeInterface;
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

  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
    private RendererInterface $renderer,
    private TimeInterface $time,
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
    $currentTime = $this->time->getCurrentTime();

    $selections = $paragraph->get('field_events')->referencedEntities();

    $ids = array_map(function (LinkedEventsEvent $event) {
      return $event->id();
    }, $selections);

    $build = [
      '#cache' => [
        'max-age' => 3600,
      ],
    ];
    $this->renderer->addCacheableDependency($build, $paragraph);
    $this->renderer->addCacheableDependency($build, $paragraph->getParentEntity());

    $storage = $this->entityTypeManager->getStorage('linkedevents_event');
    /** @var \Drupal\helfi_paragraphs_curated_event_list\Entity\LinkedEventsEvent[] $entities */
    $entities = $storage->loadMultiple($ids);

    if (!$entities) {
      $build['message'] = [
        '#markup' => new TranslatableMarkup('Recommended events were not found', options: [
          'context' => 'Curated events list empty message',
        ]),
      ];
      return $build;
    }
    // Expire in one month by default.
    $maxAge = 2.628e+6;

    foreach ($entities as $entity) {
      $entity->addCacheableDependency($paragraph);

      if ($endTime = $entity->getEndTime()?->getTimestamp()) {
        // Skip expired items.
        if ($endTime < $currentTime) {
          continue;
        }
        $newExpireTime = ($endTime - $currentTime) + 5;

        // Max-age should match the first expiring item so the block
        // is invalidated as soon as the event expires.
        if ($newExpireTime < $maxAge) {
          $maxAge = $newExpireTime;
        }
      }

      $build[] = $this->entityTypeManager->getViewBuilder('linkedevents_event')
        ->view($entity);
    }
    // Show maximum of three items.
    $build = array_slice($build, 0, 3);

    $build['#cache']['max-age'] = $maxAge;

    return $build;
  }

}
