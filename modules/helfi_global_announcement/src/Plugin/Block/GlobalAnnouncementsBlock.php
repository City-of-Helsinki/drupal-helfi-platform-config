<?php

namespace Drupal\helfi_global_announcement\Plugin\Block;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Cache\Cache;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\external_entities\ExternalEntityStorageInterface;
use Drupal\helfi_etusivu_entities\Plugin\ExternalEntities\StorageClient\Announcements;
use Drupal\helfi_node_announcement\Plugin\Block\AnnouncementsBlockBase;
use Drupal\node\Entity\Node;

/**
 * Provides 'global announcements' block.
 */
#[Block(
  id: "global_announcements",
  admin_label: new TranslatableMarkup("Global announcements"),
)]
class GlobalAnnouncementsBlock extends AnnouncementsBlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $globalEntityStorage = $this->entityTypeManager->getStorage('helfi_announcements');
    assert($globalEntityStorage instanceof ExternalEntityStorageInterface);
    $cacheMaxAge = $globalEntityStorage->getExternalEntityType()->get('persistent_cache_max_age');

    $externalAnnouncements = $globalEntityStorage
      ->loadMultiple();

    $announcementNodes = [];
    /** @var \Drupal\external_entities\ExternalEntityInterface $announcement */
    foreach ($externalAnnouncements as $announcement) {
      $linkUrl = NULL;
      $linkText = NULL;
      if ($announcement->hasField('announcement_link_text')) {
        $linkText = $announcement->get('announcement_link_text')->value;
        $linkUrl = $announcement->get('announcement_link_url')->value;
      }

      // Create announcement nodes for the block based on external entity data.
      $announcementNodes[] = Node::create([
        'uuid' => $announcement->get('uuid')->value,
        'type' => 'announcement',
        'field_announcement_type' => $announcement->get('announcement_type')->value,
        'field_announcement_link' => ['uri' => $linkUrl, 'title' => $linkText],
        'langcode' => $announcement->get('langcode')->value,
        'body' => Xss::filter($announcement->get('body')->value),
        'title' => Xss::filter($announcement->get('title')->value),
        'status' => $announcement->get('status')->value,
        'field_announcement_title' => $announcement->get('announcement_assistive_technology_close_button_title')->value,
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
