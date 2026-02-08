<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Plugin\search_api\processor;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_platform_config\TextConverter\Strategy;
use Drupal\helfi_platform_config\TextConverter\TextConverterManager;
use Drupal\helfi_search\EmbeddingsModelInterface;
use Drupal\helfi_search\MissingConfigurationException;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\Datasource\DatasourceInterface;
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

  /**
   * Text converter manager.
   */
  private TextConverterManager $textConverterManager;

  /**
   * Embeddings model.
   */
  private EmbeddingsModelInterface $embeddingModel;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $processor = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $processor->textConverterManager = $container->get(TextConverterManager::class);
    $processor->embeddingModel = $container->get(EmbeddingsModelInterface::class);
    return $processor;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL) : array {
    $properties = [];

    if (!$datasource) {
      $properties['embeddings'] = new ProcessorProperty([
        'label' => $this->t('Embeddings'),
        'description' => $this->t('Vector embeddings.'),
        'type' => 'embeddings',
        'processor_id' => $this->getPluginId(),
      ]);
    }

    return $properties;
  }

  /**
   * {@inheritDoc}
   *
   * Process field values in batches.
   */
  public function alterIndexedItems(array &$items) : void {
    foreach (array_chunk($items, 25, TRUE) as $batch) {
      $this->processBatch($items, $batch);
    }
  }

  /**
   * Process batch of items.
   *
   * @param \Drupal\search_api\Item\ItemInterface[] &$items
   *   All items.
   * @param \Drupal\search_api\Item\ItemInterface[] $batch
   *   Batch of entities.
   */
  private function processBatch(array &$items, array $batch) : void {
    // Collect chunks for each entity. Each entity may produce multiple chunks.
    $chunkBatch = [];
    $entityKeys = [];

    foreach ($batch as $key => $item) {
      try {
        /** @var \Drupal\Core\Entity\EntityInterface $entity */
        $entity = $item->getOriginalObject()->getValue();

        $chunks = $this->textConverterManager->chunk($entity, Strategy::Markdown);
        foreach ($chunks as $chunkIndex => $chunk) {
          $chunkKey = "$key:$chunkIndex";
          $chunkBatch[$chunkKey] = $chunk;
          $entityKeys[$chunkKey] = $key;
        }
      }
      catch (SearchApiException) {
        continue;
      }
    }

    try {
      $results = $this->embeddingModel->batchGetEmbedding($chunkBatch);
    }
    catch (MissingConfigurationException) {
      // Skips all items.
      $results = [];
    }

    // Group results by entity key.
    $entityResults = [];
    foreach ($results as $chunkKey => $vector) {
      $entityKey = $entityKeys[$chunkKey];
      $entityResults[$entityKey][] = [
        'vector' => $vector,
        'content' => $chunkBatch[$chunkKey],
      ];
    }

    foreach ($entityResults as $key => $embeddings) {
      if (!$item = $items[$key]) {
        throw new \LogicException("Item should exists");
      }

      $fields = $this->getFieldsHelper()
        ->filterForPropertyPath($item->getFields(), NULL, 'embeddings');

      foreach ($fields as $field) {
        foreach ($embeddings as $embedding) {
          $field->addValue($embedding);
        }
      }
    }

    // Skip items that did not produce any results, except those configured
    // to be indexed without embeddings.
    foreach (array_diff_key($batch, $entityResults) as $key => $item) {
      unset($items[$key]);
    }
  }

}
