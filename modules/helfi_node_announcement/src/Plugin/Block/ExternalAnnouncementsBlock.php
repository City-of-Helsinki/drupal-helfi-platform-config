<?php

namespace Drupal\helfi_node_announcement\Plugin\Block;

use Drupal\Core\Cache\Cache;

/**
 * Provides an 'Announcements' block.
 *
 * @Block(
 *   id = "announcements",
 *   admin_label = @Translation("Announcements"),
 * )
 */
class ExternalAnnouncementsBlock extends AnnouncementsBlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $uuids = \Drupal::entityTypeManager()
      ->getStorage('helfi_announcements')
      ->getQuery()
      ->execute();

    $externalEntityStorage = $this->entityTypeManager->getStorage('helfi_announcements');
    $cacheMaxAge = $externalEntityStorage->getExternalEntityType()->get('persistent_cache_max_age');

    $externalAnnouncements = $externalEntityStorage
      ->loadMultiple($uuids);

    $announcementNodes = [];

    foreach($externalAnnouncements as $announcement) {
      $linkUrl = null;
      $linkText = null;
      if ($announcement->hasField('announcement_link_text')) {
        $linkText = $announcement->get('announcement_link_text')->value;
        $linkUrl = $announcement->get('announcement_link_url')->value;
      }

      $announcementNodes[] = Node::create([
        'type' => 'announcement',
        'field_announcement_type' => $announcement->get('announcement_type')->value,
        'field_announcement_link' => ['uri' => $linkUrl, 'title' => $linkText],
        'langcode' => $announcement->get('langcode')->value,
        'body' => $announcement->get('body')->value,
        'title' => strip_tags(html_entity_decode($announcement->get('title')->value)),
        'status' => $announcement->get('status')->value
      ]);
    }

    if (empty($announcementNodes)) {
      return [];
    }

    $this->sortAnnouncements($announcementNodes);

    $viewMode = 'default';
    $renderArray = $this->entityTypeManager->getViewBuilder('node')->viewMultiple($announcementNodes, $viewMode);
    $renderArray['#cache']['max-age'] = $cacheMaxAge;

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
