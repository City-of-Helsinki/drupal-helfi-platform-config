<?php

namespace Drupal\helfi_announcements\Plugin\Block;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
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

    // Get all published announcement nodes.
    $nids = \Drupal::entityQuery('node')
      ->accessCheck(TRUE)
      ->condition('type', 'announcement')
      ->condition('status', NodeInterface::PUBLISHED)
      ->condition('langcode', $this->languageManager->getCurrentLanguage()->getId())
      ->execute();
    $announcementNodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);

    $showAnnouncements = [];
    foreach ($announcementNodes as $announcementNode) {
      // Check if the announcement should be shown at all pages.
      if ($announcementNode->get('field_announcement_all_pages')->value === "1") {
        $showAnnouncements[] = $announcementNode;
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
          $showAnnouncements[] = $announcementNode;
        }
      }
    }

    if (empty($showAnnouncements)) {
      return [];
    }

    $this->sortAnnouncements($showAnnouncements);
    // Set global announcements on the top.

    $viewMode = 'default';
    $renderArray = $this->entityTypeManager->getViewBuilder('node')->viewMultiple($showAnnouncements, $viewMode);

    return $renderArray;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return Cache::mergeContexts(parent::getCacheContexts(), [
      'user.permissions',
      'url.path',
      'url.query_args',
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
