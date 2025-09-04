<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_news_list\EventSubscriber;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\external_entities\Event\ExternalEntitiesEvents;
use Drupal\external_entities\Event\ExternalEntityGetMappableFieldsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Mappable field list generation event subscriber.
 */
final class MappableFieldsSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Update mappable fields.
   *
   * @param Drupal\external_entities\Event\ExternalEntityGetMappableFieldsEvent $event
   *   The event.
   */
  public function mappableFields(ExternalEntityGetMappableFieldsEvent $event): void {
    $id = $event->getEntityType()->getOriginalId();
    if ($id !== 'helfi_news_neighbourhoods') {
      return;
    }

    $fields = $event->getMappableFields() + [
      'location' => BaseFieldDefinition::create('string')
        ->setName('field_location')
        ->setTranslatable(FALSE)
        ->setCardinality(1)
        ->setLabel($this->t('Location')),
    ];

    $event->setMappableFields($fields);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ExternalEntitiesEvents::GET_MAPPABLE_FIELDS => ['mappableFields'],
    ];
  }

}
