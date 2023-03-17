<?php

declare(strict_types = 1);

namespace Drupal\helfi_react_search\EventSubscriber;

use Drupal\elasticsearch_connector\Event\PrepareIndexMappingEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribe for stripping boost parameter from Elasticsearch indices.
 *
 * Search API wants to index fields with 'boost' parameter.
 * Index-time boosting has been deprecated in newer Elasticsearch versions.
 * This subscriber strips boost from mapping to prevent errors.
 */
class BoostStripper implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    if (!class_exists('Drupal\elasticsearch_connector\Event\PrepareIndexMappingEvent')) {
      return [];
    }

    return [
      PrepareIndexMappingEvent::PREPARE_INDEX_MAPPING => 'stripBoost',
    ];
  }

  /**
   * Iterate over fields and remove any boosts.
   *
   * @param \Drupal\elasticsearch_connector\Event\PrepareIndexMappingEvent $event
   *   Event emitted by elasticsearch_connector.
   */
  public function stripBoost(PrepareIndexMappingEvent $event): void {
    $params = $event->getIndexMappingParams();

    // Bail if nothing to check against.
    if (!isset($params['body']['properties'])) {
      return;
    }

    foreach ($params['body']['properties'] as $key => $property) {
      if (isset($property['boost'])) {
        unset($params['body']['properties'][$key]['boost']);
      }
    }

    $event->setIndexMappingParams($params);
  }

}
