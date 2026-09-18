<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Plugin\search_api\processor;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_search\DocumentKeyTrait;
use Drupal\helfi_search\DocumentState;
use Drupal\helfi_search\EmbeddingModel;
use Drupal\helfi_search\Plugin\search_api\processor\DTO\StoredChunk;
use Drupal\helfi_search\Vector;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Drupal\search_api\SearchApiException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a processor for vector search.
 */
#[SearchApiProcessor(
  id: "helfi_search_embeddings",
  label: new TranslatableMarkup("Vector embeddings"),
  description: new TranslatableMarkup("Adds vector embeddings to index."),
  stages: [
    "add_properties" => 0,
    "alter_items" => 0,
  ],
)]
final class VectorEmbeddingsProcessor extends ProcessorPluginBase {

  use DocumentKeyTrait;

  /**
   * The database connection.
   */
  protected Connection $database;

  /**
   * Cached chunks read for the current batch.
   *
   * @var array<string, array<string, \Drupal\helfi_search\Plugin\search_api\processor\DTO\StoredChunk[]>>
   */
  protected array $chunks = [];

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, mixed> $configuration
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $processor = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $processor->database = $container->get('database');
    return $processor;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL): array {
    $properties = [];

    if (!$datasource) {
      foreach (EmbeddingModel::cases() as $model) {
        $properties[$model->fieldPrefix()] = new ProcessorProperty([
          'label' => $this->t('Embeddings (@model)', ['@model' => $model->value]),
          'description' => $this->t('Vector embeddings for @model.', ['@model' => $model->value]),
          'type' => 'embeddings',
          'processor_id' => $this->getPluginId(),
        ]);
      }
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, \Drupal\search_api\Item\ItemInterface<mixed>> $items
   */
  public function alterIndexedItems(array &$items): void {
    $entities = [];

    foreach ($items as $id => $item) {
      try {
        $entity = $item->getOriginalObject()->getValue();
      }
      catch (SearchApiException) {
        continue;
      }

      if ($entity instanceof ContentEntityInterface) {
        $entities[$id] = $entity;
      }
    }

    $this->chunks = [];

    foreach (EmbeddingModel::ENABLED as $model) {
      $this->chunks[$model->value] = $this->getChunks($entities, $model);
    }
  }

  /**
   * {@inheritDoc}
   *
   * @phpstan-param \Drupal\search_api\Item\ItemInterface<mixed> $item
   */
  public function addFieldValues(ItemInterface $item): void {
    $entity = $item->getOriginalObject()->getValue();
    if (!$entity instanceof ContentEntityInterface) {
      return;
    }

    $missing = FALSE;

    foreach (EmbeddingModel::ENABLED as $model) {
      $fields = $this->getFieldsHelper()
        ->filterForPropertyPath($item->getFields(FALSE), NULL, $model->fieldPrefix());

      // The model is enabled, but the index has no field for it.
      if (!$fields) {
        continue;
      }

      $chunks = $this->chunks[$model->value][$item->getId()] ?? NULL;

      if ($chunks === NULL) {
        $chunks = array_first($this->getChunks([$entity], $model));
      }
      else {
        unset($this->chunks[$model->value][$item->getId()]);
      }

      foreach ($chunks as $chunk) {
        foreach ($fields as $field) {
          $field->addValue([
            'vector' => $chunk->vector,
            'content' => $chunk->snippet ?? '',
            'fragment' => $chunk->fragment,
          ]);
        }
      }

      if (!$chunks) {
        $missing = TRUE;
      }
    }

    // Side-effect:
    // Create a row to the tracking table if we don't know about this entity
    // yet. This does not trigger re-processing if the entity already has a
    // row.
    if ($missing) {
      $key = self::key($entity);

      $this->database->merge(self::DOCUMENT_TABLE)
        ->keys($key)
        ->insertFields($key + ['state' => DocumentState::Pending->value])
        ->execute();
    }
  }

  /**
   * Reads chunks of given documents.
   *
   * @param array<\Drupal\Core\Entity\ContentEntityInterface> $entities
   *   The entities.
   * @param \Drupal\helfi_search\EmbeddingModel $model
   *   The embedding model.
   *
   * @return array<\Drupal\helfi_search\Plugin\search_api\processor\DTO\StoredChunk[]>
   *   Each document's chunks, keyed by the caller's own keys. A document
   *   that has no embedded chunks returns an empty array.
   */
  private function getChunks(array $entities, EmbeddingModel $model): array {
    if (!$entities) {
      return [];
    }

    // Each document gets its own key condition:
    // WHERE (key1 = 1a AND key2 = 1b) OR (key1 = 2a, key2 = 2b) OR ...
    $documents = $this->database->condition('OR');
    $keys = [];
    $chunks = [];

    foreach ($entities as $key => $entity) {
      $chunks[$key] = [];
      $id = self::keyId($entity);

      if (!isset($keys[$id])) {
        $documents->condition(self::keyCondition($this->database->condition('AND'), $entity));
      }

      $keys[$id][] = $key;
    }

    $rows = $this->database->select(self::CHUNK_TABLE, 'c')
      ->fields('c', [
        'entity_type',
        'entity_id',
        'langcode',
        'vector',
        'snippet',
        'fragment',
      ])
      ->condition('model', $model->value)
      ->condition($documents)
      ->orderBy('delta')
      ->execute();

    foreach ($rows as $row) {
      $id = self::keyId([
        'entity_type' => $row->entity_type,
        'entity_id' => $row->entity_id,
        'langcode' => $row->langcode,
      ]);

      $chunk = new StoredChunk(
        Vector::unpack($row->vector),
        $row->snippet,
        $row->fragment,
      );

      foreach ($keys[$id] ?? [] as $key) {
        $chunks[$key][] = $chunk;
      }
    }

    return $chunks;
  }

}
