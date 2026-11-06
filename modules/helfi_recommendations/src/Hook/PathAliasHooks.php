<?php

declare(strict_types=1);

namespace Drupal\helfi_recommendations\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\helfi_platform_config\PathAliasEntityResolver;
use Drupal\helfi_recommendations\Entity\SuggestedTopicsInterface;
use Drupal\path_alias\PathAliasInterface;
use Drupal\search_api\Plugin\search_api\datasource\ContentEntityTrackingManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Reindexes suggested topics when the path alias of their parent changes.
 *
 * Suggested topics index the URL of their parent entity.
 *
 * @see \Drupal\helfi_recommendations\Plugin\search_api\processor\ScoredReferenceParentProcessor
 */
final class PathAliasHooks {

  public function __construct(
    private readonly PathAliasEntityResolver $entityResolver,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'search_api.entity_datasource.tracking_manager')]
    private readonly ContentEntityTrackingManager $trackingManager,
  ) {
  }

  /**
   * Implements hook_ENTITY_TYPE_insert(), _update() and _delete().
   */
  #[Hook('path_alias_insert')]
  #[Hook('path_alias_update')]
  #[Hook('path_alias_delete')]
  public function reindexTopics(PathAliasInterface $alias): void {
    $storage = $this->entityTypeManager->getStorage('suggested_topics');

    foreach ($this->entityResolver->getEntities($alias) as $entity) {
      $ids = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('parent_type', $entity->getEntityTypeId())
        ->condition('parent_id', (string) $entity->id())
        ->execute();

      foreach ($storage->loadMultiple($ids) as $topic) {
        assert($topic instanceof SuggestedTopicsInterface);
        $this->trackingManager->trackEntityChange($topic);
      }
    }
  }

}
