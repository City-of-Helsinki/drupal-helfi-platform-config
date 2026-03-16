<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_curated_event_list\Entity;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\paragraphs\Entity\Paragraph;

final readonly class LazyViewBuilder implements TrustedCallbackInterface {

  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
    private TimeInterface $time,
  ) {

  }

  public function build(Paragraph $parentEntity, array $ids, int $limit = 3): array {
    $currentTime = $this->time->getCurrentTime();

    $selections = $parentEntity->get('field_events')->referencedEntities();

    $ids = array_map(function (LinkedEventsEvent $event) {
      return $event->id();
    }, $selections);

    $storage = $this->entityTypeManager->getStorage('linkedevents_event');
    /** @var \Drupal\helfi_paragraphs_curated_event_list\Entity\LinkedEventsEvent[] $entities */
    $entities = $storage->loadMultiple($ids);
    // Expire in one month by default.
    $maxAge = 2.628e+6;

    $build = [];
    foreach ($entities as $entity) {
      $entity->addCacheableDependency($parentEntity);

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
    $build = array_slice($build, 0, $limit);

    $build['#cache']['max-age'] = $maxAge;

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['build'];
  }

}
