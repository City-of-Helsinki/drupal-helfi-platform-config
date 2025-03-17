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
   *
   * @throws \Drupal\helfi_recommendations\RecommendationsException
   */
  public function processEntity(ContentEntityInterface $entity, bool $overwriteExisting = FALSE) : void;

  /**
   * Generates keywords for multiple entities.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface[] $entities
   *   The entities.
   * @param bool $overwriteExisting
   *   Overwrites existing keywords when set to TRUE.
   *
   * @throws \Drupal\helfi_recommendations\RecommendationsException
   */
  public function processEntities(array $entities, bool $overwriteExisting = FALSE) : void;

  /**
   * Get SuggestedTopics entities linked to a content entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param bool $filterEmpty
   *   If TRUE, only return topic entities that have no keywords.
   *
   * @return \Drupal\helfi_recommendations\Entity\SuggestedTopicsInterface[]
   *   Linked topics entities.
   */
  public function getSuggestedTopicsEntities(ContentEntityInterface $entity, bool $filterEmpty): array;

}
