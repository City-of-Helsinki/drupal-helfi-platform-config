<?php

declare(strict_types=1);

namespace Drupal\helfi_recommendations;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\helfi_recommendations\Entity\SuggestedTopics;
use Drupal\helfi_recommendations\Entity\SuggestedTopicsInterface;

/**
 * The reference update manager.
 *
 * This is inspired by radioactivity module.
 *
 * @see \Drupal\radioactivity\RadioactivityReferenceUpdater
 */
final class ReferenceUpdater {

  /**
   * Cached list of suggested_topics_reference fields.
   *
   * @var array|null
   */
  private array|null $referenceFields = NULL;

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo
   *   The bundle info.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    private readonly EntityFieldManagerInterface $entityFieldManager,
    private readonly EntityTypeBundleInfoInterface $entityTypeBundleInfo,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * Returns entities with suggested topics reference field(s) without target.
   *
   * @return array
   *   Structured array of entity type and IDs. Associative array keyed by
   *   "entity_type:entity_id". Values a structured array:
   *   - entity_type: The entity type.
   *   - id: The entity ID.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getReferencesWithoutTarget(): array {
    $result = [];
    $fieldNames = $this->getAllReferenceFields();

    foreach ($fieldNames as $entityType => $bundles) {
      foreach ($bundles as $bundle => $fields) {
        $ids = $this->entitiesWithNonexistentFields($entityType, $bundle, $fields);
        $result = $this->mergeEntityIds($result, $entityType, $ids);
      }
    }

    return $result;
  }

  /**
   * Returns the names of reference fields of the given entity data.
   *
   * @param string $entityType
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   *
   * @return array
   *   The field names. Empty array if this entity does not contain
   *   suggested_topics_reference fields.
   */
  public function getReferenceFields(string $entityType, string $bundle): array {
    return $this->getAllReferenceFields()[$entityType][$bundle] ?? [];
  }

  /**
   * Returns a map of reference fields per entity and bundle.
   *
   * @return array
   *   Array of annif_suggested_topics fields. Structure:
   *   - entity type: array keyed by bundle.
   *     - bundle: array of field names.
   */
  public function getAllReferenceFields(): array {
    if (!is_null($this->referenceFields)) {
      return $this->referenceFields;
    }

    // EntityFieldManagerInterface::getFieldMapByFieldType() does not work here.
    // See: https://www.drupal.org/project/drupal/issues/3045509.
    $this->referenceFields = [];
    foreach ($this->entityTypeManager->getDefinitions() as $entityTypeId => $entityType) {
      if (!$entityType->entityClassImplements(FieldableEntityInterface::class)) {
        continue;
      }

      foreach ($this->entityTypeBundleInfo->getBundleInfo($entityTypeId) as $bundle => $bundleInfo) {
        foreach ($this->entityFieldManager->getFieldDefinitions($entityTypeId, $bundle) as $fieldDefinition) {
          if ($fieldDefinition->getType() === 'suggested_topics_reference') {
            $this->referenceFields[$entityTypeId][$bundle][] = $fieldDefinition->getName();
          }
        }
      }
    }

    return $this->referenceFields;
  }

  /**
   * Returns IDs of entities where one or more fields have no value.
   *
   * @param string $entityType
   *   The entity type.
   * @param string $bundle
   *   The entity bundle.
   * @param array $fields
   *   The entity reference field names to check.
   *
   * @return array
   *   Array of entity IDs. Empty array if none were found.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function entitiesWithNonexistentFields(string $entityType, string $bundle, array $fields): array {
    $query = $this->entityTypeManager
      ->getStorage($entityType)
      ->getQuery()
      ->accessCheck(FALSE);

    $bundleKey = $this->entityTypeManager
      ->getDefinition($entityType)
      ->getKey('bundle');
    if ($bundleKey) {
      $query->condition($bundleKey, $bundle);
    }

    $orGroup = $query->orConditionGroup();
    foreach ($fields as $field) {
      $orGroup->notExists($field);
    }
    $query->condition($orGroup);

    return $query->execute();
  }

  /**
   * Merges entity IDs of multiple entity types.
   *
   * @param array $data
   *   The existing entity IDs.
   * @param string $entityType
   *   The entity type of the IDs.
   * @param array $ids
   *   The IDs to merge.
   *
   * @return array
   *   The merged data. Associative array keyed by "entity_type:entity_id".
   *   Values a structured array:
   *   - entity_type: The entity type.
   *   - id: The entity ID.
   */
  private function mergeEntityIds(array $data, string $entityType, array $ids): array {
    foreach ($ids as $id) {
      $data["$entityType:$id"] = [
        'entity_type' => $entityType,
        'id' => $id,
      ];
    }

    return $data;
  }

  /**
   * Adds missing entities to reference fields.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity to update.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  public function updateEntityReferenceFields(FieldableEntityInterface $entity): void {
    $entityIsUpdated = FALSE;
    $fieldNames = $this->getReferenceFields($entity->getEntityTypeId(), $entity->bundle());

    foreach ($fieldNames as $fieldName) {
      $referenceField = $entity->get($fieldName);
      assert($referenceField instanceof EntityReferenceFieldItemListInterface);
      if ($referenceField->isEmpty()) {
        $topicsEntity = $this->entityTypeManager
          ->getStorage('suggested_topics')
          ->create([]);

        assert($topicsEntity instanceof SuggestedTopicsInterface);
        $topicsEntity->setParentEntity($entity);
        $referenceField->setValue($topicsEntity);

        assert($topicsEntity instanceof SuggestedTopics);

        $entityIsUpdated = TRUE;
      }
    }

    if ($entityIsUpdated) {
      $entity->save();
    }
  }

}
