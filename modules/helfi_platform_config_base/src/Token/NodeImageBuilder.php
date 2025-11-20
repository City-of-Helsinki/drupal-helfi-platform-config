<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config_base\Token;

use Drupal\Core\Entity\EntityInterface;
use Drupal\file\FileInterface;
use Drupal\helfi_paragraphs_hero\Entity\Hero;
use Drupal\helfi_platform_config\Token\OGImageBuilderInterface;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;

/**
 * OG image for nodes.
 */
class NodeImageBuilder implements OGImageBuilderInterface {

  /**
   * {@inheritDoc}
   */
  public function applies(?EntityInterface $entity): bool {
    return $entity instanceof NodeInterface;
  }

  /**
   * {@inheritDoc}
   */
  public function buildUri(?EntityInterface $entity): ?string {
    assert($entity instanceof NodeInterface);

    if ($image_file = $this->getImage($entity)) {
      return $image_file->getFileUri();
    }

    return NULL;
  }

  /**
   * Get shareable image from node entity.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Node.
   *
   * @return \Drupal\file\FileInterface|null
   *   Image entity.
   */
  private function getImage(NodeInterface $node) : ?FileInterface {
    if (
      $node->hasField('field_liftup_image') &&
      isset($node->field_liftup_image->entity) &&
      $node->field_liftup_image->entity instanceof MediaInterface &&
      $node->field_liftup_image->entity->hasField('field_media_image')
    ) {
      // If liftup image has an image set, use it as the shareable image.
      $file = $node->field_liftup_image->entity->field_media_image->entity;
      assert($file instanceof FileInterface);
      return $file;
    }
    elseif (
      $node->hasField('field_image') &&
      isset($node->field_image->entity) &&
      $node->field_image->entity instanceof MediaInterface &&
      $node->field_image->entity->hasField('field_media_image')
    ) {
      // If the node has an image, use that.
      $file = $node->field_image->entity->field_media_image->entity;
      assert($file instanceof FileInterface);
      return $file;
    }
    elseif (
      $node->hasField('field_hero') &&
      isset($node->field_hero->entity) &&
      $node->field_hero->entity instanceof Hero &&
      $node->field_hero->entity->hasField('field_hero_image') &&
      isset($node->field_hero->entity->field_hero_image->entity) &&
      $node->field_hero->entity->field_hero_image->entity instanceof MediaInterface &&
      $node->field_hero->entity->field_hero_image->entity->hasField('field_media_image')
    ) {
      // If the node has a hero paragraph and the hero has an image, use that.
      $file = $node->field_hero->entity->field_hero_image->entity->field_media_image->entity;
      assert($file instanceof FileInterface);
      return $file;
    }
    elseif (
      $node->hasField('field_organization') &&
      $node->get('field_organization')?->entity?->hasField('field_default_image') &&
      !$node->get('field_organization')->entity->get('field_default_image')->isEmpty()
    ) {
      // Use the image from the taxonomy term.
      $taxonomy_term = $node->field_organization->entity;
      $file = $taxonomy_term->field_default_image->entity;
      assert($file instanceof FileInterface);
      return $file;
    }

    return NULL;
  }

}
