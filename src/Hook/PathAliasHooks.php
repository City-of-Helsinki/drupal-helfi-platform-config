<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\helfi_platform_config\PathAliasEntityResolver;
use Drupal\path_alias\PathAliasInterface;
use Drupal\search_api\Plugin\search_api\datasource\ContentEntityTrackingManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Reindexes content when its path alias changes.
 *
 * Pathauto bulk updates, the "Update URL alias" action and direct
 * pathauto.generator calls save the path alias without saving the entity it
 * points to, so Search API never marks the entity for reindexing.
 */
final class PathAliasHooks {

  public function __construct(
    private readonly PathAliasEntityResolver $entityResolver,
    // NULL when Search API is not enabled.
    #[Autowire(service: 'search_api.entity_datasource.tracking_manager')]
    private readonly ?ContentEntityTrackingManager $trackingManager = NULL,
  ) {
  }

  /**
   * Implements hook_ENTITY_TYPE_insert(), _update() and _delete().
   */
  #[Hook('path_alias_insert')]
  #[Hook('path_alias_update')]
  #[Hook('path_alias_delete')]
  public function reindex(PathAliasInterface $alias): void {
    if (!$this->trackingManager) {
      return;
    }

    foreach ($this->entityResolver->getEntities($alias) as $entity) {
      $this->trackingManager->trackEntityChange($entity);
    }
  }

}
