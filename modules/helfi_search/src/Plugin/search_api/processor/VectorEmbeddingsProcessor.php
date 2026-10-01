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
use Drupal\helfi_search\Plugin\search_api\processor\DTO\StoredDocument;
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
   * The property that holds the whole document as markdown.
   */
  public const string MARKDOWN_PROPERTY = 'helfi_search_markdown';

  /**
   * The database connection.
   */
  protected Connection $database;

  /**
   * Cached documents read for the current batch.
   *
   * @var array<string, \Drupal\helfi_search\Plugin\search_api\processor\DTO\StoredDocument>
   */
  protected array $documents = [];

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, mixed> $configuration
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $processor = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $processor->database = $container->get(Connection::class);
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

      $properties[self::MARKDOWN_PROPERTY] = new ProcessorProperty([
        'label' => $this->t('Page markdown'),
        'description' => $this->t('The document markdown.'),
        'type' => 'text',
        'processor_id' => $this->getPluginId(),
      ]);
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

    $this->documents = $this->getDocuments($entities);
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

    $document = $this->documents[$item->getId()]
      ?? array_first($this->getDocuments([$entity]))
      ?? new StoredDocument();

    // Cached document should not be needed anymore.
    unset($this->documents[$item->getId()]);

    if (!$document->hasChunks()) {
      // Side-effect:
      // Create a row to the tracking table if we don't know about this entity
      // yet. This does not trigger re-processing if the entity already has a
      // row.
      $key = self::key($entity);

      $this->database->merge(self::DOCUMENT_TABLE)
        ->keys($key)
        ->insertFields($key + ['state' => DocumentState::Pending->value])
        ->execute();

      return;
    }

    foreach (EmbeddingModel::ENABLED as $model) {
      $fields = $this->getFieldsHelper()
        ->filterForPropertyPath($item->getFields(FALSE), NULL, $model->fieldPrefix());

      // The model is enabled, but the index has no field for it.
      if (!$fields) {
        continue;
      }

      $chunks = $document->getChunks($model);

      foreach ($chunks as $chunk) {
        foreach ($fields as $field) {
          $field->addValue([
            'vector' => $chunk->vector,
            'content' => $chunk->snippet ?? '',
            'fragment' => $chunk->fragment,
          ]);
        }
      }
    }

    if ($document->markdown) {
      $fields = $this->getFieldsHelper()
        ->filterForPropertyPath($item->getFields(FALSE), NULL, self::MARKDOWN_PROPERTY);

      foreach ($fields as $field) {
        $field->addValue($document->markdown);
      }
    }
  }

  /**
   * Reads given documents and their chunks for all enabled models.
   *
   * @param array<\Drupal\Core\Entity\ContentEntityInterface> $entities
   *   The entities.
   *
   * @return array<\Drupal\helfi_search\Plugin\search_api\processor\DTO\StoredDocument>
   *   Found documents, keyed by the caller's own keys. Documents that are
   *   not stored are left out.
   */
  private function getDocuments(array $entities): array {
    if (!$entities) {
      return [];
    }

    // Each document gets its own key condition:
    // WHERE (key1 = 1a AND key2 = 1b) OR (key1 = 2a, key2 = 2b) OR ...
    $documents = $this->database->condition('OR');

    $keys = [];

    foreach ($entities as $key => $entity) {
      $documents->condition(self::keyCondition($this->database->condition('AND'), $entity));
      $keys[self::keyId($entity)] = $key;
    }

    $markdown = [];
    $chunks = [];

    $rows = $this->database->select(self::DOCUMENT_TABLE, 'd')
      ->fields('d', [
        'entity_type',
        'entity_id',
        'langcode',
        'markdown',
      ])
      ->condition($documents)
      ->execute();

    foreach ($rows as $row) {
      $markdown[self::keyId([
        'entity_type' => $row->entity_type,
        'entity_id' => $row->entity_id,
        'langcode' => $row->langcode,
      ])] = $row->markdown;
    }

    $rows = $this->database->select(self::CHUNK_TABLE, 'c')
      ->fields('c', [
        'entity_type',
        'entity_id',
        'langcode',
        'model',
        'vector',
        'snippet',
        'fragment',
      ])
      ->condition('model', array_map(static fn (EmbeddingModel $model) => $model->value, EmbeddingModel::ENABLED), 'IN')
      ->condition($documents)
      ->orderBy('delta')
      ->execute();

    foreach ($rows as $row) {
      $keyId = self::keyId([
        'entity_type' => $row->entity_type,
        'entity_id' => $row->entity_id,
        'langcode' => $row->langcode,
      ]);

      $chunks[$keyId][$row->model][] = new StoredChunk(
        Vector::unpack($row->vector),
        $row->snippet,
        $row->fragment,
      );
    }

    $result = [];

    foreach (array_keys($markdown + $chunks) as $id) {
      $result[$keys[$id]] = new StoredDocument($markdown[$id] ?? NULL, $chunks[$id] ?? []);
    }

    return $result;
  }

}
