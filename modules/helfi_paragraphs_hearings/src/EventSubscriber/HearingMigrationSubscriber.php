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
    $source = $row->getSource();
    $data = $event->getDestinationIdValues();

    $node = Node::load($data[0]);
    $url = $node->get('field_url')->getValue()[0]['uri'];
    $url .= '?lang=fi';

    foreach (['en', 'sv'] as $langcode) {
      if (!in_array("title_$langcode", $source) || !$source["title_$langcode"]) {
        continue;
      }

      $translatedUrl = str_replace('lang=fi', "lang=$langcode", $url);

      $translation = !$node->hasTranslation($langcode) ? $node->addTranslation($langcode) : $node->getTranslation($langcode);
      $translation->set('title', $source["title_$langcode"]);
      $translation->set('field_url', $translatedUrl);

      $translation->save();
    }
  }

}
