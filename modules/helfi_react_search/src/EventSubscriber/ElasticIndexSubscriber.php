<?php

declare(strict_types=1);

namespace Drupal\helfi_react_search\EventSubscriber;

use Drupal\elasticsearch_connector\Event\FieldMappingEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribe to Elastic indexing events for needed changes.
 */
class ElasticIndexSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    if (!class_exists(FieldMappingEvent::class)) {
      return [];
    }

    return [
      FieldMappingEvent::class => [['addCoordinatesField']],
    ];
  }

  /**
   * Set mapping for unit's coordinates as geo_point field.
   *
   * @param \Drupal\elasticsearch_connector\Event\FieldMappingEvent $event
   *   Event emitted by elasticsearch_connector.
   */
  public function addCoordinatesField(FieldMappingEvent $event): void {
    return;

    /** @var array $params */
    $params = $event->getIndexMappingParams();

    if ($params['index'] !== 'schools' && $params['index'] !== 'health_stations') {
      return;
    }

    $params['body']['properties']['coordinates'] = [
      'type' => 'geo_point',
    ];

    $event->setIndexMappingParams($params);
  }

}
