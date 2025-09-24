<?php

declare(strict_types=1);

namespace Drupal\helfi_recommendations;

/**
 * Provides an interface for the RecommendationsLazyBuilder service.
 */
interface RecommendationsLazyBuilderInterface {

  /**
   * Builds the recommendations block.
   *
   * @param int $userId
   *   The ID of the user.
   * @param string $entityType
   *   The type of the entity.
   * @param string $entityId
   *   The ID of the entity.
   * @param string $langcode
   *   The language code of the entity.
   *
   * @return array
   *   The recommendations block render array.
   */
  public function build(int $userId, string $entityType, string $entityId, string $langcode): array;

}
