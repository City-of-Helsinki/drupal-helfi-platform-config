<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\EventSubscriber;

use Drupal\elasticsearch_connector\Event\IndexParamsEvent;
use Drupal\elasticsearch_connector\Event\DeleteParamsEvent;
use Drupal\elasticsearch_connector\Event\BaseParamsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\helfi_api_base\Cache\CacheTagInvalidatorInterface;
use Drupal\helfi_platform_config\MultisiteSearch;

/**
 * Search api event subscriber.
 */
final class ElasticsearchParamsSubscriber implements EventSubscriberInterface {

  /**
   * Event subscriber constructor.
   *
   * @param \Drupal\helfi_platform_config\MultisiteSearch $multisiteSearch
   *   The multisite search helper.
   */
  public function __construct(
    protected MultisiteSearch $multisiteSearch,
    protected CacheTagInvalidatorInterface $cacheTagInvalidator,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Cache tag invalidation lives here, because
    // SearchApiEvents::ITEMS_INDEXED doesn't dispatch on entity deletion.
    // We need to prefix item ids first, so that we have the correct
    // item id for invalidation.
    return [
      IndexParamsEvent::class => [
        ['prefixItemIds', 1],
        ['invalidateCacheTags', 0],
      ],
      DeleteParamsEvent::class => [
        ['prefixItemIds', 1],
        ['invalidateCacheTags', 0],
      ],
    ];
  }

  /**
   * Prefix item ids.
   */
  public function prefixItemIds(BaseParamsEvent $event): void {
    $params = $event->getParams();
    $index = $event->getIndexName();

    if (!$this->multisiteSearch->isMultisiteIndex($index)) {
      return;
    }

    $params['body'] = array_map([$this, 'alterItemId'], $params['body']);

    $event->setParams($params);
  }

  /**
   * Alter item id.
   *
   * @param array $item
   *   The item.
   *
   * @return array
   *   The altered item.
   */
  private function alterItemId(array $item): array {
    if (isset($item['index'])) {
      $item['index']['_id'] = $this->multisiteSearch->addPrefixToId($item['index']['_id']);
    }
    if (isset($item['delete'])) {
      $item['delete']['_id'] = $this->multisiteSearch->addPrefixToId($item['delete']['_id']);
    }
    return $item;
  }

  /**
   * Invalidate cache tags.
   */
  public function invalidateCacheTags(BaseParamsEvent $event): void {
    $params = $event->getParams();
    $index = $event->getIndexName();
    $cache_tags = [];

    if ($index !== 'embeddings' || $params['body'] === []) {
      return;
    }

    foreach ($params['body'] as $item) {
      if (!empty($item['delete']['_id'])) {
        $cache_tags[] = 'helfi_multisite_content:' . $item['delete']['_id'];
      }
      if (!empty($item['index']['_id'])) {
        $cache_tags[] = 'helfi_multisite_content:' . $item['index']['_id'];
      }
    }

    if (!empty($cache_tags)) {
      $this->cacheTagInvalidator->invalidateTags($cache_tags);
    }
  }

}
