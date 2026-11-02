<?php

declare(strict_types=1);

namespace Drupal\helfi_recommendations\Hook;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\helfi_recommendations\TopicsManagerInterface;

/**
 * Adds suggested topics to path-alias reindexing.
 */
class PathAliasReindexHooks {

  public function __construct(
    private readonly TopicsManagerInterface $topicsManager,
  ) {
  }

  /**
   * Implements hook_helfi_platform_config_path_alias_reindex().
   *
   * @return \Drupal\helfi_recommendations\Entity\SuggestedTopicsInterface[]
   *   Suggested topics referenced by the parent entity.
   */
  #[Hook('helfi_platform_config_path_alias_reindex')]
  public function pathAliasReindex(ContentEntityInterface $entity): array {
    // Make sure suggested topics are reindexed, when the parent entity path
    // alias is updated.
    return $this->topicsManager->getSuggestedTopics($entity);
  }

}
