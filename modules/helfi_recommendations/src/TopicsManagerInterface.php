<?php

declare(strict_types=1);

namespace Drupal\helfi_recommendations;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * The topic manager interface.
 */
interface TopicsManagerInterface {

  /**
   * Queues keyword generation for single entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param bool $overwriteExisting
   *   Overwrites existing keywords when set to TRUE.
   */
  public function queueEntity(ContentEntityInterface $entity, bool $overwriteExisting = FALSE) : void;

  /**
   * Generates keywords for single entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param bool $overwriteExisting
   *   Overwrites existing keywords when set to TRUE.
   * @param bool $reset
   *   If TRUE, reset the processedItems property before processing. This
   *   allows batch processing to function properly (like drush commands and
   *   queue runners).
   *
   * @throws \Drupal\helfi_recommendations\RecommendationsException
   */
  public function processEntity(ContentEntityInterface $entity, bool $overwriteExisting = FALSE, bool $reset = FALSE) : void;

  /**
   * Generates keywords for multiple entities.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface[] $entities
   *   The entities.
   * @param bool $overwriteExisting
   *   Overwrites existing keywords when set to TRUE.
   * @param bool $reset
   *   If TRUE, reset the processedItems property before processing. This
   *   allows batch processing to function properly (like drush commands and
   *   queue runners).
   *
   * @throws \Drupal\helfi_recommendations\RecommendationsException
   */
  public function processEntities(array $entities, bool $overwriteExisting = FALSE, bool $reset = FALSE) : void;

  /**
   * Get keyword data for a content entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return array
   *   Array of keyword data.
   */
  public function getKeywords(ContentEntityInterface $entity): array;

  /**
   * Get the topics reference fields of an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\Core\Field\EntityReferenceFieldItemListInterface[]
   *   Array of topics reference fields.
   */
  public function getTopicsReferenceFields(ContentEntityInterface $entity): array;

}
