<?php

namespace Drupal\helfi_global_announcement\Plugin\Block;

use Drupal\Core\Cache\Cache;
use Drupal\helfi_node_announcement\Plugin\Block\AnnouncementsBlockBase;
use Drupal\helfi_node_announcement\Plugin\ExternalEntities\StorageClient\Announcements;
use Drupal\node\Entity\Node;

/**
 * Provides 'global announcements' block.
 *
 * @Block(
 *   id = "global_announcements",
 *   admin_label = @Translation("Global announcements"),
 * )
 */
class GlobalAnnouncementsBlock extends AnnouncementsBlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $uuids = \Drupal::entityTypeManager()
      ->getStorage('helfi_announcements')
      ->getQuery()
      ->execute();

    $globalEntityStorage = $this->entityTypeManager->getStorage('helfi_announcements');
    $cacheMaxAge = $globalEntityStorage->getExternalEntityType()->get('persistent_cache_max_age');

    $externalAnnouncements = $globalEntityStorage
      ->loadMultiple($uuids);

    $announcementNodes = [];

    foreach ($externalAnnouncements as $announcement) {
      $linkUrl = NULL;
      $linkText = NULL;
      if ($announcement->hasField('announcement_link_text')) {
        $linkText = $announcement->get('announcement_link_text')->value;
        $linkUrl = $announcement->get('announcement_link_url')->value;
      }

      $announcementNodes[] = Node::create([
        'type' => 'announcement',
        'field_announcement_type' => $announcement->get('announcement_type')->value,
        'field_announcement_link' => ['uri' => $linkUrl, 'title' => $linkText],
        'langcode' => $announcement->get('langcode')->value,
        'body' => strip_tags(html_entity_decode($announcement->get('body')->value)),
        'title' => strip_tags(html_entity_decode($announcement->get('title')->value)),
        'status' => $announcement->get('status')->value,
      ]);
    }

    $viewMode = 'default';
    $renderArray = $this->entityTypeManager->getViewBuilder('node')->viewMultiple($announcementNodes, $viewMode);

    $renderArray['#cache'] = [
      'max-age' => $cacheMaxAge,
      'tags' => [
        Announcements::$customCacheTag,
      ],
    ];

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
