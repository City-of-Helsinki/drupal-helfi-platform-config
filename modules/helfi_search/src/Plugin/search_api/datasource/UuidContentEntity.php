<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Plugin\search_api\datasource;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\external_entities\Entity\Query\External\Query as ExternalEntitiesQuery;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Plugin\search_api\datasource\ContentEntity;
use Drupal\search_api\Utility\Utility;

/**
 * Represents a datasource which exposes the content entities indexed by uuid.
 *
 * We want to store items from multiple instances in the same index. To avoid
 * conflicts, we want to use the uuid of the item as the id.
 *
 * @SearchApiDatasource(
 *   id = "uuid_entity",
 *   deriver = "Drupal\helfi_search\Plugin\search_api\datasource\UuidContentEntityDeriver"
 * )
 */
class UuidContentEntity extends ContentEntity {

  /**
   * {@inheritdoc}
   */
  protected const string TRACKING_PAGE_STATE_KEY = 'search_api.datasource.uuid_entity.last_ids';

  /**
   * Load multiple entities by their uuids.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   The loaded entities.
   */
  private function loadMultipleByUuids($ids): array {
    $storage = $this->getEntityStorage();
    $entity_type = $this->getEntityType();
    $key = $entity_type->getKey('uuid');

    $ids = $storage
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition($key, $ids, 'IN')
      ->execute();

    if (empty($ids)) {
      return [];
    }

    $entities = [];

    foreach ($storage->loadMultiple($ids) as $entity) {
      $entities[$entity->uuid()] = $entity;
    }

    return $entities;
  }

  /**
   * {@inheritdoc}
   *
   * This is copied from the parent class, but it uses uuid instead of id as the
   * item ID.
   */
  public function loadMultiple(array $ids) {
    $allowed_languages = $this->getLanguages();

    $entity_ids = [];
    foreach ($ids as $item_id) {
      $pos = strrpos($item_id, ':');
      // This can only happen if someone passes an invalid ID, since we always
      // include a language code. Still, no harm in guarding against bad input.
      if ($pos === FALSE) {
        continue;
      }
      $entity_id = substr($item_id, 0, $pos);
      $langcode = substr($item_id, $pos + 1);
      if (isset($allowed_languages[$langcode])) {
        $entity_ids[$entity_id][$item_id] = $langcode;
      }
    }

    /** @var \Drupal\Core\Entity\ContentEntityInterface[] $entities */
    $entities = $this->loadMultipleByUuids(array_keys($entity_ids));
    $items = [];
    $allowed_bundles = $this->getBundles();
    foreach ($entity_ids as $entity_id => $langcodes) {
      if (empty($entities[$entity_id]) || !isset($allowed_bundles[$entities[$entity_id]->bundle()])) {
        continue;
      }
      foreach ($langcodes as $item_id => $langcode) {
        if ($entities[$entity_id]->hasTranslation($langcode)) {
          $items[$item_id] = $entities[$entity_id]->getTranslation($langcode)->getTypedData();
        }
      }
    }

    return $items;
  }

  /**
   * {@inheritdoc}
   */
  public function getItemId(ComplexDataInterface $item) {
    if ($entity = $this->getEntity($item)) {
      $langcode = $entity->language()->getId();
      if (isset($this->getBundles()[$entity->bundle()])
        && isset($this->getLanguages()[$langcode])) {
        return static::formatItemId($entity->getEntityTypeId(), $entity->uuid(), $langcode);
      }
    }

    return NULL;
  }

  /**
   * {@inheritDoc}
   *
   * This is copied from the parent class, but it uses uuid instead of id as the
   * item ID.
   */
  public function getPartialItemIds($page = NULL, ?array $bundles = NULL, ?array $languages = NULL) {
    // These would be pretty pointless calls, but for the sake of completeness
    // we should check for them and return early. (Otherwise makes the rest of
    // the code more complicated.)
    if (($bundles === [] && !$languages) || ($languages === [] && !$bundles)) {
      return NULL;
    }

    $entity_type = $this->getEntityType();
    $entity_id = $entity_type->getKey('uuid');

    // Use a direct database query when an entity has a defined base table. This
    // should prevent performance issues associated with the use of entity query
    // on large data sets. This allows for better control over what tables are
    // included in the query.
    // If no base table is present, then perform an entity query instead.
    if ($entity_type->getBaseTable()
      && empty($this->configuration['disable_db_tracking'])) {
      $select = $this->getDatabaseConnection()
        ->select($entity_type->getBaseTable(), 'base_table')
        ->fields('base_table', [$entity_id]);
    }
    else {
      $select = $this->getEntityTypeManager()
        ->getStorage($this->getEntityTypeId())
        ->getQuery();
      // When tracking items, we never want access checks.
      $select->accessCheck(FALSE);
    }

    // Build up the context for tracking the last ID for this batch page.
    $batch_page_context = [
      'index_id' => $this->getIndex()->id(),
      // The derivative plugin ID includes the entity type ID.
      'datasource_id' => $this->getPluginId(),
      'bundles' => $bundles,
      'languages' => $languages,
    ];
    $context_key = Crypt::hashBase64(serialize($batch_page_context));
    $last_ids = $this->getState()->get(self::TRACKING_PAGE_STATE_KEY, []);

    // We want to determine all entities of either one of the given bundles OR
    // one of the given languages. That means we can't just filter for $bundles
    // if $languages is given. Instead, we have to filter for all bundles we
    // might want to include and later sort out those for which we want only the
    // translations in $languages and those (matching $bundles) where we want
    // all (enabled) translations.
    if ($this->hasBundles()) {
      $bundle_property = $entity_type->getKey('bundle');
      if ($bundles && !$languages) {
        $select->condition($bundle_property, $bundles, 'IN');
      }
      else {
        $enabled_bundles = array_keys($this->getBundles());
        // Since this is also called for removed bundles/languages,
        // $enabled_bundles might not include $bundles.
        if ($bundles) {
          $enabled_bundles = array_unique(array_merge($bundles, $enabled_bundles));
        }
        if (count($enabled_bundles) < count($this->getEntityBundles())) {
          $select->condition($bundle_property, $enabled_bundles, 'IN');
        }
      }
    }

    if (isset($page)) {
      $page_size = $this->getConfigValue('tracking_page_size');
      assert($page_size, 'Tracking page size is not set.');

      // If known, use a condition on the last tracked ID for paging instead of
      // the offset, for performance reasons on large sites.
      $offset = $page * $page_size;
      if ($page > 0) {
        // We only handle the case of picking up from where the last page left
        // off. (This will cause an infinite loop if anyone ever wants to index
        // Search API tasks in an index, so check for that to be on the safe
        // side. Also, the external_entities module doesn't reliably support
        // conditions on entity queries, so disable this functionality in that
        // case, too.)
        if (isset($last_ids[$context_key])
          && $last_ids[$context_key]['page'] == ($page - 1)
          && $this->getEntityTypeId() !== 'search_api_task'
          && !($select instanceof ExternalEntitiesQuery)) {
          $select->condition($entity_id, $last_ids[$context_key]['last_id'], '>');
          $offset = 0;
        }
      }
      $select->range($offset, $page_size);

      // For paging to reliably work, a sort should be present.
      if ($select instanceof SelectInterface) {
        $select->orderBy($entity_id);
      }
      else {
        $select->sort($entity_id);
      }
    }

    if ($select instanceof SelectInterface) {
      $entity_ids = $select->execute()->fetchCol();
    }
    else {
      $entity_ids = $select->execute();
    }

    if (!$entity_ids) {
      if (isset($page)) {
        // Clean up state tracking of last ID.
        unset($last_ids[$context_key]);
        $this->getState()->set(self::TRACKING_PAGE_STATE_KEY, $last_ids);
      }
      return NULL;
    }

    // Remember the last tracked ID for the next call.
    if (isset($page)) {
      $last_ids[$context_key] = [
        'page' => (int) $page,
        'last_id' => end($entity_ids),
      ];
      $this->getState()->set(self::TRACKING_PAGE_STATE_KEY, $last_ids);
    }

    // For all loaded entities, compute all their item IDs (one for each
    // translation we want to include). For those matching the given bundles (if
    // any), we want to include translations for all enabled languages. For all
    // other entities, we just want to include the translations for the
    // languages passed to the method (if any).
    $item_ids = [];
    $enabled_languages = array_keys($this->getLanguages());
    // As above for bundles, $enabled_languages might not include $languages.
    if ($languages) {
      $enabled_languages = array_unique(array_merge($languages, $enabled_languages));
    }

    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    foreach ($this->loadMultipleByUuids($entity_ids) as $entity_id => $entity) {
      $translations = array_keys($entity->getTranslationLanguages());
      $translations = array_intersect($translations, $enabled_languages);
      // If only languages were specified, keep only those translations matching
      // them. If bundles were also specified, keep all (enabled) translations
      // for those entities that match those bundles.
      if ($languages !== NULL
        && (!$bundles || !in_array($entity->bundle(), $bundles))) {
        $translations = array_intersect($translations, $languages);
      }
      foreach ($translations as $langcode) {
        $item_ids[] = static::formatItemId($entity->getEntityTypeId(), $entity->uuid(), $langcode);
      }
    }

    if (Utility::isRunningInCli()) {
      // When running in the CLI, this might be executed for all entities from
      // within a single process. To avoid running out of memory, reset the
      // static cache after each batch.
      $this->getEntityMemoryCache()->deleteAll();
    }

    return $item_ids;
  }

  /**
   * {@inheritdoc}
   *
   * This is copied from the parent class, but it uses uuid instead of id as the
   * item ID.
   */
  public function getAffectedItemsForEntityChange(EntityInterface $entity, array $foreign_entity_relationship_map, ?EntityInterface $original_entity = NULL): array {
    if (!($entity instanceof ContentEntityInterface)) {
      return [];
    }

    $ids_to_reindex = [];
    $path_separator = IndexInterface::PROPERTY_PATH_SEPARATOR;
    foreach ($foreign_entity_relationship_map as $relation_info) {
      // Ignore relationships belonging to other datasources.
      if (!empty($relation_info['datasource'])
        && $relation_info['datasource'] !== $this->getPluginId()) {
        continue;
      }
      // Check whether entity type and (if specified) bundles match the entity.
      if ($relation_info['entity_type'] !== $entity->getEntityTypeId()) {
        continue;
      }
      if (!empty($relation_info['bundles'])
        && !in_array($entity->bundle(), $relation_info['bundles'])) {
        continue;
      }
      // Maybe this entity belongs to a bundle that does not have this field
      // attached. Hence, we have this check to ensure the field is present on
      // this particular entity.
      if (!$entity->hasField($relation_info['field_name'])) {
        continue;
      }

      $items = $entity->get($relation_info['field_name']);

      // We trigger re-indexing if either it is a removed entity or the
      // entity has changed its field value (in case it's an update).
      if (!$original_entity || !$items->equals($original_entity->get($relation_info['field_name']))) {
        $query = $this->entityTypeManager->getStorage($this->getEntityTypeId())
          ->getQuery();
        $query->accessCheck(FALSE);

        // Luckily, to translate from property path to the entity query
        // condition syntax, all we have to do is replace the property path
        // separator with the entity query path separator (a dot) and that's it.
        $property_path = $relation_info['property_path_to_foreign_entity'];
        $property_path = str_replace($path_separator, '.', $property_path);
        $query->condition($property_path, $entity->id());

        try {
          $entity_ids = array_values($query->execute());
        }
        // @todo Switch back to \Exception once Core bug #2893747 is fixed.
        catch (\Throwable $e) {
          // We don't want to catch all PHP \Error objects thrown, but just the
          // ones caused by #2893747.
          if (!($e instanceof \Exception)
            && (get_class($e) !== \Error::class || $e->getMessage() !== 'Call to a member function getColumns() on bool')) {
            throw $e;
          }
          $vars = [
            '%index' => $this->index->label() ?? $this->index->id(),
            '%entity_type' => $entity->getEntityType()->getLabel(),
            '@entity_id' => $entity->id(),
          ];
          try {
            $link = $entity->toLink($this->t('Go to changed %entity_type with ID "@entity_id"', $vars))
              ->toString()->getGeneratedLink();
          }
          catch (\Throwable) {
            // Ignore any errors here, it's not that important that the log
            // message contains a link.
            $link = NULL;
          }
          $this->logException($e, '%type while attempting to find indexed entities referencing changed %entity_type with ID "@entity_id" for index %index: @message in %function (line %line of %file).', $vars, RfcLogLevel::ERROR, $link);
          continue;
        }
        foreach ($entity_ids as $entity_id) {
          foreach ($this->getLanguages() as $language) {
            $ids_to_reindex[static::formatItemId($this->getEntityTypeId(), $entity_id, $language->getId())] = 1;
          }
        }
      }
    }
    $ids_to_reindex = array_keys($ids_to_reindex);

    if ($ids_to_reindex) {
      $combined_ids = array_map($this->createCombinedId(...), $ids_to_reindex);
      $this->getIndex()->registerUnreliableItemIds($combined_ids);
    }

    return $ids_to_reindex;
  }

}
