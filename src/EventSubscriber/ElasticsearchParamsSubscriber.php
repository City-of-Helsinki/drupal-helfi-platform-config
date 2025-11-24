<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\EventSubscriber;

use Drupal\elasticsearch_connector\Event\IndexParamsEvent;
use Drupal\elasticsearch_connector\Event\DeleteParamsEvent;
use Drupal\elasticsearch_connector\Event\BaseParamsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
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
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      IndexParamsEvent::class => 'prefixItemIds',
      DeleteParamsEvent::class => 'prefixItemIds',
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

}
