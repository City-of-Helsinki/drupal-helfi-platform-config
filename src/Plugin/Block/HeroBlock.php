<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_platform_config\EntityVersionMatcher;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Provides a 'HeroBlock' block.
 */
#[Block(
  id: "hero_block",
  admin_label: new TranslatableMarkup("Hero block"),
)]
class HeroBlock extends ContentBlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $build = [];

    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    ['entity' => $entity, 'entity_version' => $entity_version] = $this->getCurrentEntityVersion();

    // No need to continue if current entity doesn't have hero field.
    if (
      !$entity instanceof ContentEntityInterface ||
      !$entity->hasField('field_hero')
    ) {
      return $build;
    }

    // @todo Support preview on entity reference fields ie. paragraphs.
    if (
      !$entity->hasField('field_has_hero') ||
      !$entity->get('field_has_hero')->isEmpty()
    ) {
      $first_paragraph_grey = '';

      // Handle only landing page.
      if (
        $entity->bundle() === 'landing_page' &&
        !$entity->get('field_content')->isEmpty()
      ) {
        // Check if the content field first paragraph is Unit search
        // and add classes accordingly.
        $paragraph = $entity->get('field_content')->entity;
        $paragraphs_with_grey_bg = [
          'unit_search',
          'service_list_search',
        ];

        // Let modules alter the array of paragraphs with grey background.
        $this->moduleHandler->alter('first_paragraph_grey', $paragraphs_with_grey_bg);

        if (
          $paragraph instanceof ParagraphInterface &&
          in_array($paragraph->getType(), $paragraphs_with_grey_bg)
        ) {
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
