<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\EventSubscriber;

use Drupal\elasticsearch_connector\Event\FieldMappingEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to field mapping events to add geo_shape support.
 */
final class FieldMappingSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      FieldMappingEvent::class => 'onFieldMapping',
    ];
  }

  /**
   * Handles field mapping for geo_shape type.
   *
   * @param \Drupal\elasticsearch_connector\Event\FieldMappingEvent $event
   *   The field mapping event.
   */
  public function onFieldMapping(FieldMappingEvent $event): void {
    $field = $event->getField();

    if ($field->getType() === 'geo_shape') {
      $event->setParam([
        'type' => 'geo_shape',
      ]);
    }
  }

}
