<?php

declare(strict_types=1);

namespace Drupal\helfi_recommendations\EventSubscriber;

use Drupal\search_api\Event\MappingFieldTypesEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\elasticsearch_connector\Event\FieldMappingEvent;

/**
 * Search api event subscriber.
 */
final class SearchApiSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $map = [];

    // Make sure the class exists before subscribing to events.
    // @see: https://www.drupal.org/project/drupal/issues/2825358
    if (class_exists('\Drupal\search_api\Event\SearchApiEvents', TRUE)) {
      $map[SearchApiEvents::MAPPING_FIELD_TYPES] = 'mapFieldTypes';
    }
    if (class_exists('\Drupal\elasticsearch_connector\Event\FieldMappingEvent', TRUE)) {
      $map[FieldMappingEvent::class] = 'mapElasticFields';
    }

    return $map;
  }

  /**
   * Map custom field types.
   */
  public function mapFieldTypes(MappingFieldTypesEvent $event): void {
    $mapping = &$event->getFieldTypeMapping();
    $mapping['scored_item'] = 'scored_item';
  }

  /**
   * Map Elasticsearch fields.
   */
  public function mapElasticFields(FieldMappingEvent $event): void {
    $field = $event->getField();
    $type = $field->getType();

    // Make sure scored_item uses 'nested' type.
    if ($type === 'scored_item') {
      $param = $event->getParam();
      $param['type'] = 'nested';
      $event->setParam($param);
    }
  }

}
