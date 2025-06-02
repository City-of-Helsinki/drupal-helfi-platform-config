<?php

declare(strict_types=1);

namespace Drupal\helfi_etusivu_entities\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Cache\Cache;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_etusivu_entities\Plugin\ExternalEntities\StorageClient\Surveys;
use Drupal\helfi_etusivu_entities\SurveyLazyBuilder;

/**
 * Provides a 'Surveys' block.
 */
#[Block(
  id: "surveys",
  admin_label: new TranslatableMarkup("Surveys"),
)]
final class SurveyBlock extends EtusivuEntityBlockBase {

  /**
   * Entity types supported by survey and the corresponding field names.
   */
  public const ENTITY_TYPE_FIELDS = [
    'node' => 'field_survey_content_pages',
  ];

  /**
   * {@inheritDoc}
   */
  public function build(): array {
    $useRemoteEntities = $this->configuration['use_remote_entities'];
    return [
      '#lazy_builder' => [SurveyLazyBuilder::class . '::lazyBuild', [$useRemoteEntities]],
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
      Surveys::$customCacheTag,
      'node_list:survey',
    ]);
  }

}
