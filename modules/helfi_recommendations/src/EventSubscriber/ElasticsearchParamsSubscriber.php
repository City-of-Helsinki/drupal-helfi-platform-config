<?php

declare(strict_types=1);

namespace Drupal\helfi_recommendations\EventSubscriber;

use Drupal\Component\Uuid\Uuid;
use Drupal\elasticsearch_connector\Event\IndexParamsEvent;
use Drupal\elasticsearch_connector\Event\DeleteParamsEvent;
use Drupal\elasticsearch_connector\Event\BaseParamsEvent;
use Drupal\helfi_recommendations\RecommendationManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Elasticsearch params subscriber.
 */
class ElasticsearchParamsSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected RecommendationManagerInterface $recommendationManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Cache tag invalidation lives here, because
    // SearchApiEvents::ITEMS_INDEXED doesn't dispatch on entity deletion.
    return [
      IndexParamsEvent::class => 'invalidateCacheTags',
      DeleteParamsEvent::class => 'invalidateCacheTags',
    ];
  }

  /**
   * Invalidate cache tags.
   */
  public function invalidateCacheTags(BaseParamsEvent $event): void {
    $params = $event->getParams();
    $index = $event->getIndexName();
    $uuids = [];

    if ($index !== 'suggestions' || $params['body'] === []) {
      return;
    }

    foreach ($params['body'] as $item) {
      // Collect both deleted and indexed ids.
      $ids = [];
      if (!empty($item['delete']['_id'])) {
        $ids[] = $item['delete']['_id'];
      }
      if (!empty($item['index']['_id'])) {
        $ids[] = $item['index']['_id'];
      }
      if (empty($ids)) {
        continue;
      }

      foreach ($ids as $id) {
        // The id format is 'entity:suggested_topics/uuid:langcode'. Let's
        // extract the uuid and validate it.
        $parts = explode(':', $id);
        if (!isset($parts[1]) || !str_starts_with($parts[1], 'suggested_topics/')) {
          continue;
        }

        $uuid = str_replace('suggested_topics/', '', $parts[1]);
        if (!Uuid::isValid($uuid)) {
          continue;
        }

        $uuids[] = $uuid;
      }
    }

    if (!empty($uuids)) {
      $this->recommendationManager->invalidateExternalCacheTags($uuids);
    }
  }

}
