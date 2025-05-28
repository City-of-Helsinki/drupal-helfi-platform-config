<?php

declare(strict_types=1);

namespace Drupal\helfi_etusivu_entities\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Cache\Cache;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_etusivu_entities\AnnouncementsLazyBuilder;
use Drupal\helfi_etusivu_entities\Plugin\ExternalEntities\StorageClient\Announcements;

/**
 * Provides an 'Announcements' block.
 */
#[Block(
  id: "announcements",
  admin_label: new TranslatableMarkup("Announcements"),
)]
final class AnnouncementsBlock extends EtusivuEntityBlockBase {

  public const VISIBILITY_ALL_WEIGHT = 0;
  public const VISIBILITY_REGION_WEIGHT = 1;
  public const VISIBILITY_PAGE_WEIGHT = 2;

  /**
   * Entity types supported by announcements and the corresponding field names.
   */
  public const ENTITY_TYPE_FIELDS = [
    'node' => 'field_announcement_content_pages',
    'tpr_unit' => 'field_announcement_unit_pages',
    'tpr_service' => 'field_announcement_service_pages',
  ];

  /**
   * {@inheritDoc}
   */
  public function build(): array {
    $useRemoteEntities = $this->configuration['use_remote_entities'];
    return [
      '#lazy_builder' => [AnnouncementsLazyBuilder::class . '::lazyBuild', [$useRemoteEntities]],
      '#create_placeholder' => TRUE,
      '#lazy_builder_preview' => ['#markup' => ''],
    ];
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
    return Cache::mergeTags(parent::getCacheTags(), [
      'node_list:announcement',
      Announcements::$customCacheTag,
    ]);
  }

}
