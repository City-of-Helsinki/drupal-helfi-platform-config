<?php

namespace Drupal\helfi_paragraphs_hearings\EventSubscriber;

use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Drupal\node\Entity\Node;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class HearingMigrationSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      MigrateEvents::POST_ROW_SAVE => 'handleTranslations',
    ];
  }

  /**
   * Handle hearing translations.
   *
   * @param MigratePostRowSaveEvent $event
   * @return void
   */
  public function handleTranslations(MigratePostRowSaveEvent $event): void {
    $row = $event->getRow();
    $data = $event->getDestinationIdValues();
    $node = Node::create([]);

    foreach (['en', 'sv'] as $langcode) {
      if (!in_array("title_$langcode", $data)) {
        continue;
      }

      if (!$node->hasTranslation($langcode)) {
        // $translation = $node->addTranslation();
      }
    }
  }

}
