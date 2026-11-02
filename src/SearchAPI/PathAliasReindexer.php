<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\SearchAPI;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Url;
use Drupal\path_alias\PathAliasInterface;
use Drupal\search_api\Plugin\search_api\datasource\ContentEntityTrackingManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Reindexes Search API items when a path alias changes.
 *
 * Pathauto bulk updates, the "Update URL alias" action, and direct
 * pathauto.generator calls save the path_alias entity without saving the
 * content entity it points at. Search API therefore never marks that content
 * for reindexing.
 *
 * Other modules can return additional content entities from
 * hook_helfi_platform_config_path_alias_reindex(). Those entities are queued
 * on the same tracking manager.
 */
class PathAliasReindexer {

  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ContainerInterface $container,
  ) {
  }

  /**
   * Reindexes the content behind a path alias, and related entities.
   */
  public function reindex(EntityInterface $entity): void {
    if (!$entity instanceof PathAliasInterface || !$this->moduleHandler->moduleExists('search_api')) {
      return;
    }
    if (!$this->container->has('search_api.entity_datasource.tracking_manager')) {
      return;
    }
    $tracking_manager = $this->container->get('search_api.entity_datasource.tracking_manager');
    if (!$tracking_manager instanceof ContentEntityTrackingManager) {
      return;
    }

    $aliases = [$entity];
    $original = $entity->getOriginal();
    if ($original instanceof PathAliasInterface && $original->getPath() !== $entity->getPath()) {
      $aliases[] = $original;
    }
    foreach ($aliases as $alias) {
      $content = $this->loadContentEntityFromPath($alias->getPath());
      if (!$content instanceof ContentEntityInterface) {
        continue;
      }
      $tracking_manager->entityUpdate($content);
      foreach ($this->moduleHandler->invokeAll('helfi_platform_config_path_alias_reindex', [$content]) as $related) {
        if ($related instanceof ContentEntityInterface) {
          $tracking_manager->entityUpdate($related);
        }
      }
    }
  }

  /**
   * Loads the content entity for an internal system path.
   */
  private function loadContentEntityFromPath(string $path): ?ContentEntityInterface {
    try {
      $parameters = Url::fromUserInput($path)->getRouteParameters();
    }
    catch (\InvalidArgumentException | \UnexpectedValueException) {
      return NULL;
    }

    foreach ($parameters as $entity_type_id => $parameter) {
      if ((!is_string($parameter) && !is_int($parameter)) || !$this->entityTypeManager->hasDefinition($entity_type_id)) {
        continue;
      }
      try {
        $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($parameter);
      }
      catch (InvalidPluginDefinitionException | PluginNotFoundException) {
        continue;
      }
      if ($entity instanceof ContentEntityInterface) {
        return $entity;
      }
    }
    return NULL;
  }

}
