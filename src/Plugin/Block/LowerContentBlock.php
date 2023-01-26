<?php

declare(strict_types = 1);

namespace Drupal\helfi_platform_config\Plugin\Block;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\helfi_platform_config\EntityVersionMatcher;
use Drupal\helfi_tpr\Entity\Service;
use Drupal\helfi_tpr\Entity\Unit;

/**
 * Provides a 'LowerContentBlock' block.
 *
 * @Block(
 *  id = "lower_content_block",
 *  admin_label = @Translation("Lower content block"),
 * )
 */
class LowerContentBlock extends ContentBlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() : array {
    $build['lower_content'] = [
      '#theme' => 'lower_content_block',
      '#title' => $this->t('Lower content block'),
    ];

    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    ['entity' => $entity, 'entity_version' => $entity_version] = $this->getCurrentEntityVersion();

    // Pass the Unit entity render array to templates if one exists.
    if ($entity instanceof Unit) {
      $view_builder = $this->entityTypeManager->getViewBuilder('tpr_unit');
      $build['lower_content']['#computed'] = $view_builder->view($entity);
      $build['lower_content']['#computed']['#theme'] = 'tpr_unit_lower_content';
    }

    // Pass the Service entity render array to templates if one exists.
    if ($entity instanceof Service) {
      $view_builder = $this->entityTypeManager->getViewBuilder('tpr_service');
      $build['lower_content']['#computed'] = $view_builder->view($entity);
      $build['lower_content']['#computed']['#theme'] = 'tpr_service_lower_content';
    }

    // Add the lower content paragraphs to render array.
    if (
      $entity instanceof ContentEntityInterface &&
      $entity->hasField('field_lower_content')
    ) {
      $build['lower_content'] = $build['lower_content'] + [
        '#is_revision' => $entity_version == EntityVersionMatcher::ENTITY_VERSION_REVISION,
        '#paragraphs' => $entity->get('field_lower_content'),
        '#cache' => [
          'tags' => $entity->getCacheTags(),
        ],
      ];
    }

    return $build;
  }

}
