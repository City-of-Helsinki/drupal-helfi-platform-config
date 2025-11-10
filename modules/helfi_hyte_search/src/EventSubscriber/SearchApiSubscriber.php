<?php

declare(strict_types=1);

namespace Drupal\helfi_hyte_search\EventSubscriber;

use Drupal\elasticsearch_connector\Event\IndexParamsEvent;
use Drupal\elasticsearch_connector\Event\DeleteParamsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Search api event subscriber.
 */
final class SearchApiSubscriber implements EventSubscriberInterface {

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
  public function prefixItemIds(IndexParamsEvent|DeleteParamsEvent $event): void {
    $params = $event->getParams();
    $prefix = '';

    try {
      $environmentResolver = \Drupal::service('helfi_api_base.environment_resolver');
      $project = $environmentResolver->getActiveProject();
      $projectName = $project->getName();
      $prefix = $projectName . ':';
    } catch (\Exception $e) {
      $prefix = '';
    }

    $params['body'] = array_map(function ($item) use ($prefix) {
      if (isset($item['index'])) {
        $item['index']['_id'] = $prefix . $item['index']['_id'];
      }
      if (isset($item['delete'])) {
        $item['delete']['_id'] = $prefix . $item['delete']['_id'];
      }
      return $item;
    }, $params['body']);

    $event->setParams($params);
  }

}
