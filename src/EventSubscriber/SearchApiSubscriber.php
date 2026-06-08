<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\EventSubscriber;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\elasticsearch_connector\Event\FieldMappingEvent;
use Drupal\elasticsearch_connector\Event\SupportsDataTypeEvent;
use Drupal\search_api\Event\GatheringPluginInfoEvent;
use Drupal\search_api\Event\MappingFieldTypesEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Search api event subscriber.
 */
final class SearchApiSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
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
      $events[SearchApiEvents::GATHERING_PROCESSORS] = 'onGatheringProcessors';
    }

    // Subscribe to elasticsearch_connector's SupportsDataTypeEvent.
    if (class_exists(SupportsDataTypeEvent::class)) {
      $events[SupportsDataTypeEvent::class] = 'onSupportsDataType';
    }

    // Subscribe to elasticsearch_connector's FieldMappingEvent.
    if (class_exists(FieldMappingEvent::class)) {
      $events[FieldMappingEvent::class] = 'onFieldMapping';
    }

    return $events;
  }

  /**
   * Removes processors that depend on optional, uninstalled modules.
   *
   * This lets us ship the metatag title processor without a hard dependency on
   * the metatag module.
   */
  public function onGatheringProcessors(GatheringPluginInfoEvent $event): void {
    if ($this->moduleHandler->moduleExists('metatag')) {
      return;
    }

    $definitions = &$event->getDefinitions();
    unset($definitions['helfi_search_metatag_title']);
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

}
