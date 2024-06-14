<?php

declare(strict_types=1);

namespace Drupal\helfi_etusivu_entities\Plugin\Block;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Cache\Cache;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_etusivu_entities\Plugin\ExternalEntities\StorageClient\Surveys;
use Drupal\node\Entity\Node;

/**
 * Provides 'global announcements' block.
 */
#[Block(
  id: "global_surveys",
  admin_label: new TranslatableMarkup("Global surveys"),
)]
class GlobalSurveysBlock extends EtusivuEntityBlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $entityStorage = $this->getGlobalEntityStorage('helfi_surveys');
    $nodes = [];

    /** @var \Drupal\external_entities\ExternalEntityInterface $entity */
    foreach ($entityStorage->loadMultiple() as $entity) {
      $linkUrl = NULL;
      $linkText = NULL;
      if ($entity->hasField('survey_link_text')) {
        $linkText = $entity->get('survey_link_text')->value;
        $linkUrl = $entity->get('survey_link_url')->value;
      }

      // Create announcement nodes for the block based on external entity data.
      $nodes[] = Node::create([
        'uuid' => $entity->get('uuid')->value,
        'type' => 'survey',
        'field_survey_link' => ['uri' => $linkUrl, 'title' => $linkText],
        'langcode' => $entity->get('langcode')->value,
        'body' => Xss::filter($entity->get('body')->value),
        'title' => Xss::filter($entity->get('title')->value),
        'status' => $entity->get('status')->value,
      ]);
    }

    $viewMode = 'default';
    $renderArray = $this->entityTypeManager->getViewBuilder('node')->viewMultiple($nodes, $viewMode);
    $renderArray['#cache'] = [
      'max-age' => $entityStorage->getExternalEntityType()->get('persistent_cache_max_age'),
      'tags' => [
        Surveys::$customCacheTag,
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
    return Cache::mergeTags(parent::getCacheTags(), ['node_list:survey']);
  }

}
