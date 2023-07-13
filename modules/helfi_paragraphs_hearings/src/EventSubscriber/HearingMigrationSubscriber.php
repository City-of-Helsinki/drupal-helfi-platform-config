<?php

namespace Drupal\helfi_paragraphs_hearings\EventSubscriber;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\File\FileSystem;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateImportEvent;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Drupal\node\Entity\Node;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class HearingMigrationSubscriber implements EventSubscriberInterface {

  /**
   *
   */
  const HEARING_MIGRATION = 'helfi_hearings';

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\File\FileSystem $fileSystem
   *   The file system service.
   * @param Drupal\Core\Config\ConfigFactory $config
   *   The settings service.
   */
  public function __construct(private FileSystem $fileSystem, private ConfigFactory $config) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      MigrateEvents::POST_ROW_SAVE => 'handleTranslations',
      MigrateEvents::PRE_IMPORT => 'preImport',
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

  /**
   * Ensure that the directory for job listing images exists.
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $event
   *   The event object.
   */
  public function preImport(MigrateImportEvent $event): void {
    if (
      $event->getMigration()->id() == self::HEARING_MIGRATION &&
      !file_exists($this->fileSystem->realpath($this->getImagesDir()))
    ) {
      $this->fileSystem->mkdir($this->getImagesDir());
    }
  }

  /**
   * Return the uri for images folder.
   *
   * @return string
   *   The uri.
   */
  protected function getImagesDir(): string {
    $defaultScheme = $this->config->get('system.file')->get('default_scheme');
    return "$defaultScheme://hearing_images/";
  }

}
