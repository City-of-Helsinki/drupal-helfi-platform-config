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
