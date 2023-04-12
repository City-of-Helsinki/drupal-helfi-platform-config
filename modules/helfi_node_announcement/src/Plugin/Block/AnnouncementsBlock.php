<?php

namespace Drupal\helfi_node_announcement\Plugin\Block;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Language\LanguageInterface;
use Drupal\node\NodeInterface;

/**
 * Provides an 'Announcements' block.
 *
 * @Block(
 *   id = "announcements",
 *   admin_label = @Translation("Announcements"),
 * )
 */
class AnnouncementsBlock extends AnnouncementsBlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    // Entity types supported by announcements and the corresponding field
    // names.
    $entityTypeFields = [
      'node' => 'field_announcement_content_pages',
      'tpr_unit' => 'field_announcement_unit_pages',
      'tpr_service' => 'field_announcement_service_pages',
    ];

    $currentEntity = $this->getCurrentPageEntity(array_keys($entityTypeFields));
    $langcode = $this->languageManager
      ->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)
      ->getId();

    // Get all published announcement nodes.
    $nids = \Drupal::entityQuery('node')
      ->accessCheck(TRUE)
      ->condition('type', 'announcement')
      ->condition('status', NodeInterface::PUBLISHED)
      ->condition('langcode', $langcode)
      ->execute();
    $announcementNodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);

    $localAnnouncements = [];
    $globalAnnouncements = [];

    foreach ($announcementNodes as $announcementNode) {
      // Check if the announcement should be shown at all pages.
      // Global announcements should be shown on top of all pages.
      if (
        $announcementNode->hasField('field_publish_externally')
        && $announcementNode->get('field_publish_externally')->value
      ) {
        $globalAnnouncements[] = $announcementNode;
        continue;
      }

      if ($announcementNode->get('field_announcement_all_pages')->value === "1") {
        $localAnnouncements[] = $announcementNode;
        continue;
      }

      if (empty($currentEntity)) {
        continue;
      }

      // Get announcement's referenced entities from the appropriate field,
      // depending on the current page's entity.
      $referencedEntities = [];
      if ($announcementNode->hasField($entityTypeFields[$currentEntity->getEntityType()->id()])) {
        $referencedEntities = $announcementNode->get($entityTypeFields[$currentEntity->getEntityType()->id()])->referencedEntities();
      }

      // Add announcement to showed announcements if current page's entity
      // is found from the list of referenced entities.
      foreach ($referencedEntities as $referencedEntity) {
        if ($referencedEntity->id() === $currentEntity->id()) {
          $localAnnouncements[] = $announcementNode;
        }
      }
    }

    $this->sortAnnouncements($localAnnouncements);

    $viewMode = 'default';
    $renderArray = $this->entityTypeManager->getViewBuilder('node')
      ->viewMultiple(array_merge($globalAnnouncements, $localAnnouncements), $viewMode);

    return $renderArray;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return Cache::mergeContexts(parent::getCacheContexts(), [
      'url.path',
      'languages:language_content',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return Cache::mergeTags(parent::getCacheTags(), ['node_list:announcement']);
  }

}
