<?php

declare(strict_types=1);

namespace Drupal\helfi_recommendations;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * The recommendation manager interface.
 */
interface RecommendationManagerInterface {

  /**
   * Check if recommendations should be shown.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return bool
   *   Whether recommendations should be shown.
   */
  public function showRecommendations(ContentEntityInterface $entity): bool;

  /**
   * Get recommendations for a node.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The node.
   * @param int $limit
   *   How many recommendations should be returned.
   * @param string|null $target_langcode
   *   Which translation to use to select the recommendations,
   *   null uses the entity's translation.
   * @param array $options
   *   Additional options to limit recommendations.
   *
   * @return array
   *   Array of recommendations.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getRecommendations(ContentEntityInterface $entity, int $limit = 3, ?string $target_langcode = NULL, ?array $options = []): array;

  /**
   * Get the cache tag for a UUID.
   *
   * @param string $uuid
   *   The UUID.
   *
   * @return string
   *   The cache tag.
   */
  public function getCacheTagForUUID(string $uuid): string;

  /**
   * Invalidate external cache tags.
   *
   * @param array $uuids
   *   The UUIDs.
   */
  public function invalidateExternalCacheTags(array $uuids): void;

}
