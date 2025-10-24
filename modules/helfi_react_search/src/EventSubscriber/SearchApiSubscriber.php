<?php

declare(strict_types=1);

namespace Drupal\helfi_react_search\EventSubscriber;

use Drupal\elasticsearch_connector\Event\FieldMappingEvent;
use Drupal\search_api\Event\MappingFieldTypesEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Drupal\search_api\SearchApiException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Search api event subscriber.
 */
final class SearchApiSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Bail if search_api is not installed.
    if (!class_exists(SearchApiEvents::class)) {
      return [];
    }

    return [
      SearchApiEvents::MAPPING_FIELD_TYPES => 'mapFieldTypes',
      FieldMappingEvent::class => 'alterFieldMapping',
    ];
  }

  /**
   * Map custom field types.
   *
   * @param \Drupal\search_api\Event\MappingFieldTypesEvent $event
   *   The mapping field types event.
   */
  public function mapFieldTypes(MappingFieldTypesEvent $event): void {
    $mapping = &$event->getFieldTypeMapping();
    $mapping['object'] = 'object';
  }

  /**
   * Modify field mapping.
   *
   * Try fetching properties for nested items from data definition.
   *
   * @param \Drupal\elasticsearch_connector\Event\FieldMappingEvent $event
   *   The field mapping event.
   *
   * @see Drupal\helfi_react_search\Plugin\search_api\processor\ChannelsForService::getPropertyDefinitions()
   * @see Drupal\helfi_react_search\Plugin\search_api\processor\UnitsForService::getPropertyDefinitions()
   */
  public function alterFieldMapping(FieldMappingEvent $event): void {
    $field = $event->getField();
    $param = $event->getParam();

    try {
      $dataDefinition = $field->getDataDefinition();
    }
    catch (SearchApiException $e) {
      return;
    }

    if (!$dataDefinition || !$dataDefinition['nested_properties']) {
      return;
    }

    $param['properties'] = $dataDefinition['nested_properties'];
    $event->setParam($param);
  }

}
