<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\path_alias\PathAliasInterface;

/**
 * Resolves the content entities a path alias points to.
 */
final class PathAliasEntityResolver {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * Gets the content entities whose URL is changed by the given alias.
   *
   * @param \Drupal\path_alias\PathAliasInterface $alias
   *   The inserted, updated or deleted path alias.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface[]
   *   The content entities. If the alias was moved to another path, this
   *   includes the entity of the original path too.
   */
  public function getEntities(PathAliasInterface $alias): array {
    $paths = [$alias->getPath()];

    $original = $alias->getOriginal();
    if ($original instanceof PathAliasInterface) {
      $paths[] = $original->getPath();
    }

    $entities = [];
    foreach (array_unique($paths) as $path) {
      if ($entity = $this->loadEntity($path)) {
        $entities[] = $entity;
      }
    }
    return $entities;
  }

  /**
   * Loads the content entity of the given system path.
   *
   * @param string $path
   *   The system path, for example '/node/1'.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   The entity, or NULL if the path is not a canonical entity route.
   */
  private function loadEntity(string $path): ?ContentEntityInterface {
    try {
      $url = Url::fromUri('internal:' . $path);
    }
    catch (\InvalidArgumentException) {
      return NULL;
    }

    // Only the canonical route is the URL of the entity.
    if (!$url->isRouted() || !preg_match('/^entity\.(\w+)\.canonical$/', $url->getRouteName(), $matches)) {
      return NULL;
    }
    [, $entity_type_id] = $matches;
    $id = $url->getRouteParameters()[$entity_type_id] ?? NULL;

    if ($id === NULL || !$this->entityTypeManager->hasDefinition($entity_type_id)) {
      return NULL;
    }
    $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($id);

    return $entity instanceof ContentEntityInterface ? $entity : NULL;
  }

}
