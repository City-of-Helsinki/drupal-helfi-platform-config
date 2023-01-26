<?php

declare(strict_types = 1);

namespace Drupal\helfi_platform_config\Plugin\Block;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\helfi_platform_config\EntityVersionMatcher;

/**
 * Provides a 'HeroBlock' block.
 *
 * @Block(
 *  id = "hero_block",
 *  admin_label = @Translation("Hero block"),
 * )
 */
class HeroBlock extends ContentBlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() : array {
    $build = [];

    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    ['entity' => $entity, 'entity_version' => $entity_version] = $this->getCurrentEntityVersion();

    // No need to continue if current entity doesn't have has_hero field.
    if (
      !$entity instanceof ContentEntityInterface ||
      !$entity->hasField('field_has_hero')
    ) {
      return $build;
    }

    // @todo Support preview on entity reference fields ie. paragraphs.
    if (!$entity->get('field_has_hero')->isEmpty()) {
      $first_paragraph_grey = '';

      // Handle only landing page.
      if (
        $entity->getType() === 'landing_page' &&
        !$entity->get('field_content')->isEmpty()
      ) {
        // Check if the content field first paragraph is Unit search
        // and add classes accordingly.
        $paragraph = $entity->get('field_content')->entity;
        if (!empty($paragraph) && $paragraph->getType() === 'unit_search') {
          $first_paragraph_grey = 'has-first-gray-bg-block';
        }
      }

      $build['hero_block'] = [
        '#theme' => 'hero_block',
        '#title' => $this->t('Hero block'),
        '#paragraphs' => $entity->get('field_hero'),
        '#is_revision' => $entity_version == EntityVersionMatcher::ENTITY_VERSION_REVISION,
        '#first_paragraph_grey' => $first_paragraph_grey,
        '#cache' => [
          'tags' => $entity->getCacheTags(),
        ],
      ];
    }

    return $build;
  }

}
