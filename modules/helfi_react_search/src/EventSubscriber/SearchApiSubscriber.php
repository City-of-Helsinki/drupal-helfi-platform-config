<?php

declare(strict_types=1);

namespace Drupal\helfi_react_search\EventSubscriber;

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
    // Bail if search_api is not installed.
    if (!class_exists(SearchApiEvents::class)) {
      return [];
    }

    return [
      SearchApiEvents::MAPPING_FIELD_TYPES => 'mapFieldTypes',
    ];
  }

  /**
   * Map custom field types.
   */
  public function mapFieldTypes(MappingFieldTypesEvent $event): void {
    $mapping = &$event->getFieldTypeMapping();
    $mapping['object'] = 'object';
  }

}
