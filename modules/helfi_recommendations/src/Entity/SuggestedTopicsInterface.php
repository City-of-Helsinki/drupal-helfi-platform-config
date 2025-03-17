<?php

declare(strict_types=1);

namespace Drupal\helfi_recommendations\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;

/**
 * Provides an interface defining a suggestted topics entity type.
 */
interface SuggestedTopicsInterface extends ContentEntityInterface, EntityPublishedInterface {

  /**
   * Check if the entity has keywords.
   *
   * @return bool
   *   Entity has keywords.
   */
  public function hasKeywords(): bool;

  /**
   * Get the keywords of the entity.
   *
   * @return array
   *   The keywords.
   */
  public function getKeywords(): array;

  /**
   * Set the parent entity of the item.
   *
   * @param \Drupal\Core\Entity\EntityInterface $parent
   *   The parent entity.
   *
   * @return $this
   */
  public function setParentEntity(EntityInterface $parent): self;

}
