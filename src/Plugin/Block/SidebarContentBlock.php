<?php

declare(strict_types = 1);

namespace Drupal\helfi_platform_config\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\helfi_platform_config\EntityVersionMatcher;
use Drupal\helfi_tpr\Entity\Service;
use Drupal\helfi_tpr\Entity\Unit;

/**
 * Provides a 'SidebarContentBlock' block.
 *
 * @Block(
 *  id = "sidebar_content_block",
 *  admin_label = @Translation("Sidebar content block"),
 * )
 */
class SidebarContentBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() : array {
    $matcher = \Drupal::service('helfi_platform_config.entity_version_matcher')->getType();

    if (
      !$matcher['entity'] ||
      $matcher['entity_version'] == EntityVersionMatcher::ENTITY_VERSION_REVISION
    ) {
      return parent::getCacheTags();
    }
    return Cache::mergeTags(parent::getCacheTags(), $matcher['entity']->getCacheTags());
  }

  /**
   * {@inheritDoc}
   */
  public function getCacheContexts() : array {
    return Cache::mergeContexts(parent::getCacheContexts(), ['route']);
  }

  /**
   * {@inheritdoc}
   */
  public function build() : array {
    $build['sidebar_content'] = [
      '#theme' => 'sidebar_content_block',
      '#title' => $this->t('Sidebar content block'),
    ];

    // Get current entity and entity version.
    $entity_matcher = \Drupal::service('helfi_platform_config.entity_version_matcher')->getType();

    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $entity_matcher['entity'];
    $entity_version = $entity_matcher['entity_version'];

    // Pass Unit entity render array to templates.
    if ($entity instanceof Unit) {
      $view_builder = \Drupal::entityTypeManager()->getViewBuilder('tpr_unit');
      $build['sidebar_content']['#computed'] = $view_builder->view($entity);
      $build['sidebar_content']['#computed']['#theme'] = 'tpr_unit_contact_information';
    }

    // Pass the Service entity render array to templates if one exists.
    if ($entity instanceof Service) {
      $view_builder = \Drupal::entityTypeManager()->getViewBuilder('tpr_service');
      $build['sidebar_content']['#computed'] = $view_builder->view($entity);
      $build['sidebar_content']['#computed']['#theme'] = 'tpr_service_important_links';
    }

    // Add the sidebar content paragraphs to render array.
    if (
      $entity instanceof ContentEntityInterface &&
      $entity->hasField('field_sidebar_content')
    ) {
      $build['sidebar_content'] = $build['sidebar_content'] + [
        '#is_revision' => $entity_version == EntityVersionMatcher::ENTITY_VERSION_REVISION,
        '#paragraphs' => $entity->get('field_sidebar_content'),
        '#cache' => [
          'tags' => $entity->getCacheTags(),
        ],
      ];
    }

    return $build;
  }

}