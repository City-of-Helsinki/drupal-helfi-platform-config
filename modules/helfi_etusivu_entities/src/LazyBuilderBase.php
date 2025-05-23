<?php

declare(strict_types=1);

namespace Drupal\helfi_etusivu_entities;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\external_entities\ExternalEntityStorageInterface;

/**
 * Base class for lazy builder.
 */
class LazyBuilderBase implements TrustedCallbackInterface {

  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
    private RouteMatchInterface $routeMatch,
  ) {
  }

  /**
   * Get global entity storage.
   *
   * @param string $entityTypeId
   *   External entity type.
   */
  protected function getExternalEntityStorage(string $entityTypeId): ExternalEntityStorageInterface {
    $globalEntityStorage = $this->entityTypeManager->getStorage($entityTypeId);
    if ($globalEntityStorage instanceof ExternalEntityStorageInterface) {
      return $globalEntityStorage;
    }

    throw new \InvalidArgumentException("$entityTypeId is not external entity type");
  }

  /**
   * Get current page's entity from given possibilities.
   *
   * @param array $entityTypes
   *   Entity names to be used to check the current page.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   Current page's entity, if any.
   */
  protected function getCurrentPageEntity(array $entityTypes): ?EntityInterface {
    foreach ($entityTypes as $entityType) {
      $pageEntity = $this->routeMatch->getParameter($entityType);
      if (!empty($pageEntity) && $pageEntity instanceof EntityInterface) {
        return $pageEntity;
      }
    }
    return NULL;
  }

  /**
   * Checks if entity has reference to another entity.
   *
   * @param string $fieldName
   *   Entity reference field name.
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity.
   * @param \Drupal\Core\Entity\EntityInterface|null $target
   *   Target entity.
   *
   * @return bool
   *   True if $entity has reference to $target.
   */
  protected function hasReference(string $fieldName, FieldableEntityInterface $entity, ?EntityInterface $target): bool {
    // Get announcement's referenced entities from the appropriate field,
    // depending on the current page's entity.
    $referencedEntities = [];

    if ($entity->hasField($fieldName)) {
      $field = $entity->get($fieldName);
      assert($field instanceof EntityReferenceFieldItemListInterface);
      $referencedEntities = $field->referencedEntities();
    }

    if ($target) {
      foreach ($referencedEntities as $referencedEntity) {
        if ($referencedEntity->id() === $target->id()) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public static function trustedCallbacks(): array {
    return ['lazyBuild'];
  }

}
