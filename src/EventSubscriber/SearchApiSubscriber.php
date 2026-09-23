<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\EventSubscriber;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\elasticsearch_connector\Event\FieldMappingEvent;
use Drupal\elasticsearch_connector\Event\SupportsDataTypeEvent;
use Drupal\helfi_api_base\Cache\CacheTagInvalidatorInterface;
use Drupal\helfi_platform_config\MultisiteContentId;
use Drupal\helfi_platform_config\MultisiteSearch;
use Drupal\search_api\Event\MappingFieldTypesEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Drupal\search_api\Event\ItemsIndexedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Search api event subscriber.
 */
final class SearchApiSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected CacheTagInvalidatorInterface $cacheTagInvalidator,
    protected MultisiteSearch $multisiteSearch,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = [];

    // Bail if search_api is not installed.
    if (class_exists(SearchApiEvents::class)) {
      $events[SearchApiEvents::MAPPING_FIELD_TYPES] = 'mapFieldTypes';
    }

    // Subscribe to elasticsearch_connector's SupportsDataTypeEvent.
    if (class_exists(SupportsDataTypeEvent::class)) {
      $events[SupportsDataTypeEvent::class] = 'onSupportsDataType';
    }

    // Subscribe to elasticsearch_connector's FieldMappingEvent.
    if (class_exists(FieldMappingEvent::class)) {
      $events[FieldMappingEvent::class] = 'onFieldMapping';
    }

    // Subscribe to search_api's ItemsIndexedEvent.
    if (class_exists(SearchApiEvents::class)) {
      $events[SearchApiEvents::ITEMS_INDEXED] = 'onItemsIndexed';
    }

    return $events;
  }

  /**
   * Map custom field types.
   */
  public function mapFieldTypes(MappingFieldTypesEvent $event): void {
    $mapping = &$event->getFieldTypeMapping();
    $mapping['location'] = 'location';
    $mapping['computed_geo_shape'] = 'geo_shape';
  }

  /**
   * Mark geo_shape as a supported data type for Elasticsearch backend.
   */
  public function onSupportsDataType(SupportsDataTypeEvent $event): void {
    if ($event->getType() === 'geo_shape') {
      $event->setIsSupported(TRUE);
    }
  }

  /**
   * Map geo_shape field to Elasticsearch geo_shape type.
   */
  public function onFieldMapping(FieldMappingEvent $event): void {
    $field = $event->getField();
    if ($field->getType() === 'geo_shape') {
      $event->setParam(['type' => 'geo_shape']);
    }
  }

  /**
   * Invalidate cache tags when items are indexed.
   */
  public function onItemsIndexed(ItemsIndexedEvent $event): void {
    $index = $event->getIndex();
    if ($index->id() !== 'embeddings') {
      return;
    }

    $processed_ids = $event->getProcessedIds();
    if ($processed_ids === []) {
      return;
    }

    // Embeddings index is used as a source for a Linkit matcher, so we need
    // to invalidate the cache tags for the multisite content entities to make
    // sure the links are rendered from latest index data.
    $is_multisite_index = $this->multisiteSearch->isMultisiteIndex($index->id());
    $entity_ids = [];
    foreach ($processed_ids as $id) {
      $id = (string) $id;
      $entity_ids[] = $is_multisite_index
        ? $this->multisiteSearch->addPrefixToId($id)
        : $id;
    }

    $entities = $this->entityTypeManager
      ->getStorage(MultisiteContentId::ENTITY_TYPE_ID)
      ->loadMultiple($entity_ids);

    $cache_tags = [];
    foreach ($entities as $entity) {
      if (!$entity instanceof EntityInterface) {
        continue;
      }
      $cache_tags = Cache::mergeTags($cache_tags, array_values($entity->getCacheTagsToInvalidate()));
    }

    if ($cache_tags !== []) {
      $this->cacheTagInvalidator->invalidateTags($cache_tags);
    }
  }

}
