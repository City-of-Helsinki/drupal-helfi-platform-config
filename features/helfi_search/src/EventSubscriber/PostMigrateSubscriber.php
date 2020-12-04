<?php

namespace Drupal\helfi_search\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\migrate\Event\EventBase;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateImportEvent;
use Drupal\search_api\Entity\Index;

class PostMigrateSubscriber implements EventSubscriberInterface {
  /**
   * {@inheritdoc}
   *
   * Returns an array of event names this subscriber wants to listen to.
   */
  public static function getSubscribedEvents() {
    $events[MigrateEvents::POST_IMPORT] = ['onImportFinished'];
    return $events;
  }

  public function onImportFinished(MigrateImportEvent $event) {
    if ($event->getMigration()->migration_group == 'ahjo') {
      $search_api_index = Index::load('ahjo_issues');
      $search_api_index->indexItems();  
    }
  }
}