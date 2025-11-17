<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Plugin\search_api\datasource;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\search_api\Plugin\search_api\datasource\ContentEntityTrackingManager;

/**
 * Provides hook implementations on behalf of the Content Entity datasource.
 *
 * @see \Drupal\helfi_search\Plugin\search_api\datasource\UuidContentEntity
 */
class UuidContentEntityTrackingManager extends ContentEntityTrackingManager {

  /**
   * {@inheritdoc}
   */
  protected const string DATASOURCE_BASE_ID = 'uuid_entity';

  /**
   * {@inheritdoc}
   */
  public function trackEntityChange(ContentEntityInterface $entity, bool $new = FALSE): void {
    // Check if the entity is a content entity.
    if (!empty($entity->search_api_skip_tracking)) {
      return;
    }

    $indexes = $this->getIndexesForEntity($entity);
    if (!$indexes) {
      return;
    }

    // Compare old and new languages for the entity to identify inserted,
    // updated and deleted translations (and, therefore, search items).
    $new_translations = array_keys($entity->getTranslationLanguages());
    $old_translations = [];
    if (!$new) {
      // In case we don't have the original, fall back to the current entity,
      // and assume no new translations were added.
      $original = DeprecationHelper::backwardsCompatibleCall(
        \Drupal::VERSION,
        '11.2',
        fn () => $entity->getOriginal() ?: $entity,
        fn () => $entity->original ?? $entity,
      );
      $old_translations = array_keys($original->getTranslationLanguages());
    }
    $deleted_translations = array_diff($old_translations, $new_translations);
    $inserted_translations = array_diff($new_translations, $old_translations);
    $updated_translations = array_diff($new_translations, $inserted_translations);

    $datasource_id = static::DATASOURCE_BASE_ID . ':' . $entity->getEntityTypeId();
    $get_ids = function (string $langcode) use ($entity): string {
      return static::formatItemId($entity->getEntityTypeId(), $entity->uuid(), $langcode);
    };
    $inserted_ids = array_map($get_ids, $inserted_translations);
    $updated_ids = array_map($get_ids, $updated_translations);
    $deleted_ids = array_map($get_ids, $deleted_translations);

    foreach ($indexes as $index) {
      if ($inserted_ids) {
        $filtered_item_ids = static::filterValidItemIds($index, $datasource_id, $inserted_ids);
        if ($filtered_item_ids) {
          $index->trackItemsInserted($datasource_id, $filtered_item_ids);
        }
      }
      if ($updated_ids) {
        $filtered_item_ids = static::filterValidItemIds($index, $datasource_id, $updated_ids);
        if ($filtered_item_ids) {
          $index->trackItemsUpdated($datasource_id, $filtered_item_ids);
        }
      }
      if ($deleted_ids) {
        $filtered_item_ids = static::filterValidItemIds($index, $datasource_id, $deleted_ids);
        if ($filtered_item_ids) {
          $index->trackItemsDeleted($datasource_id, $filtered_item_ids);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function entityDelete(EntityInterface $entity): void {
    // Check if the entity is a content entity.
    if (!($entity instanceof ContentEntityInterface)
        || !empty($entity->search_api_skip_tracking)) {
      return;
    }

    $indexes = $this->getIndexesForEntity($entity);
    if (!$indexes) {
      return;
    }

    // Remove the search items for all the entity's translations.
    $item_ids = [];
    $entity_id = $entity->uuid();
    foreach (array_keys($entity->getTranslationLanguages()) as $langcode) {
      $item_ids[] = static::formatItemId($entity->getEntityTypeId(), $entity_id, $langcode);
    }
    $datasource_id = static::DATASOURCE_BASE_ID . ':' . $entity->getEntityTypeId();
    foreach ($indexes as $index) {
      $index->trackItemsDeleted($datasource_id, $item_ids);
    }
  }

}
