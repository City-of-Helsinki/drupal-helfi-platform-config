<?php

declare(strict_types=1);

namespace Drupal\helfi_search\EventSubscriber;

use Drupal\elasticsearch_connector\Event\FieldMappingEvent;
use Drupal\elasticsearch_connector\Event\SupportsDataTypeEvent;
use Drupal\search_api\Event\MappingFieldTypesEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Search api event subscriber.
 */
final class SearchApiSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[SupportsDataTypeEvent::class] = 'supportsDataType';
    $events[FieldMappingEvent::class] = 'mapElasticFields';

    if (class_exists(SearchApiEvents::class)) {
      $events[SearchApiEvents::MAPPING_FIELD_TYPES] = 'mapFieldTypes';
    }

    return $events;
  }

  /**
   * Map custom field types.
   */
  public function mapFieldTypes(MappingFieldTypesEvent $event): void {
    $mapping = &$event->getFieldTypeMapping();
    $mapping['embeddings'] = 'embeddings';
  }

  /**
   * Add support for embeddings data type.
   */
  public function supportsDataType(SupportsDataTypeEvent $event): void {
    if ($event->getType() === 'embeddings') {
      $event->setIsSupported(TRUE);
    }
  }

  /**
   * Map embeddings type to dense vector.
   */
  public function mapElasticFields(FieldMappingEvent $event): void {
    if ($event->getField()->getType() === 'embeddings') {
      $event->setParam([
        'type' => 'nested',
        'properties' => [
          'vector' => [
            'type' => 'dense_vector',
          ],
          'content' => [
            'type' => 'text',
            'index' => FALSE,
          ],
        ],
      ]);
    }
  }

}
