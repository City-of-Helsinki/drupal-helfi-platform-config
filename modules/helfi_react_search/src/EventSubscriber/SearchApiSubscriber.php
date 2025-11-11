<?php

declare(strict_types=1);

namespace Drupal\helfi_react_search\EventSubscriber;

use Drupal\elasticsearch_connector\Event\FieldMappingEvent;
use Drupal\elasticsearch_connector\Event\SupportsDataTypeEvent;
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
      SupportsDataTypeEvent::class => 'alterSupportsDataType',
    ];
  }

  /**
   * Map custom field types.
   *
   * @param \Drupal\search_api\Event\MappingFieldTypesEvent $event
   *   The mapping field types event.
   */
  public function mapFieldTypes(MappingFieldTypesEvent $event): void {
    // Make sure object field type from elasticsearch_connector has correct
    // mapping. Without this the field configuration form will not allow adding
    // any object-type fields.
    $mapping = &$event->getFieldTypeMapping();
    $mapping['object'] = 'object';
    $mapping['comma_separated_string'] = 'string';
  }

  /**
   * Modify field mapping.
   *
   * Try fetching properties for nested items from data definition.
   *
   * @param \Drupal\elasticsearch_connector\Event\FieldMappingEvent $event
   *   The field mapping event.
   */
  public function alterFieldMapping(FieldMappingEvent $event): void {
    $this->modifyFieldMappingForNestedItems($event);
    $this->modifyFieldMappingForCommaSeparatedString($event);
  }

  /**
   * Modify field mapping for nested items.
   *
   * @param \Drupal\elasticsearch_connector\Event\FieldMappingEvent $event
   *   The field mapping event.
   *
   * @see Drupal\helfi_react_search\Plugin\search_api\processor\ChannelsForService::getPropertyDefinitions()
   * @see Drupal\helfi_react_search\Plugin\search_api\processor\UnitsForService::getPropertyDefinitions()
   */
  private function modifyFieldMappingForNestedItems(FieldMappingEvent $event): void {
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

    // These are custom properties that allow configuring subfield properties
    // for object-type (nested) fields.
    $param['properties'] = $dataDefinition['nested_properties'];
    $event->setParam($param);
  }

  /**
   * Modify field mapping for comma separated string.
   *
   * @param \Drupal\elasticsearch_connector\Event\FieldMappingEvent $event
   *   The field mapping event.
   *
   * @see Drupal\helfi_react_search\Plugin\search_api\data_type\CommaSeparatedStringDataType
   */
  private function modifyFieldMappingForCommaSeparatedString(FieldMappingEvent $event): void {
    $field = $event->getField();
    $type = $field->getType();
    if ($type === 'comma_separated_string') {
      $param = [
        'type' => 'keyword',
      ];
      $event->setParam($param);
    }
  }

  /**
   * Alter supports data type.
   *
   * Add support for custom data types.
   *
   * @param \Drupal\elasticsearch_connector\Event\SupportsDataTypeEvent $event
   *   The supports data type event.
   *
   * @see Drupal\helfi_react_search\Plugin\search_api\data_type\CommaSeparatedStringDataType
   */
  public function alterSupportsDataType(SupportsDataTypeEvent $event): void {
    if ($event->getType() === 'comma_separated_string') {
      $event->setIsSupported(TRUE);
    }
  }

}
