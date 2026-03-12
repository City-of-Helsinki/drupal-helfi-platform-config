<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Plugin\search_api\processor;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_search\EmbeddingsModelException;
use Drupal\helfi_search\EmbeddingsModelInterface;
use Drupal\helfi_search\OpenAI\EmbeddingsApi;
use Drupal\helfi_search\Pipeline\PipelineException;
use Drupal\helfi_search\Pipeline\TextPipeline;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
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
    // This should be called after alter plugins that filter items.
    "alter_items" => 999,
  ],
)]
final class VectorEmbeddingsProcessor extends ProcessorPluginBase {

  /**
   * Text pipeline.
   */
  private TextPipeline $textPipeline;

  /**
   * Embeddings model.
   */
  private EmbeddingsModelInterface $embeddingsModel;

  /**
   * Config factory.
   */
  private ConfigFactoryInterface $configFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $processor = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $processor->textPipeline = $container->get(TextPipeline::class);
    $processor->embeddingsModel = $container->get(EmbeddingsModelInterface::class);
    $processor->configFactory = $container->get(ConfigFactoryInterface::class);
    return $processor;
  }

  /**
   * Get configured models.
   *
   * @return string[]
   *   Model names.
   */
  private function getModels(): array {
    return $this->configFactory->get('helfi_search.settings')->get('openai_models') ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL): array {
    $properties = [];

    if (!$datasource) {
      foreach ($this->getModels() as $model) {
        $suffix = EmbeddingsApi::sanitizeModelName($model);
        $fieldName = 'embeddings_' . $suffix;

        $properties[$fieldName] = new ProcessorProperty([
          'label' => $this->t('Embeddings (@model)', ['@model' => $model]),
          'description' => $this->t('Vector embeddings for @model.', ['@model' => $model]),
          'type' => 'embeddings',
          'processor_id' => $this->getPluginId(),
        ]);
      }
    }

    return $properties;
  }

  /**
   * {@inheritDoc}
   *
   * Process items in batches of 25 through the text-to-vector pipeline.
   */
  public function alterIndexedItems(array &$items): void {
    $models = $this->getModels();

    if (empty($models)) {
      return;
    }

    foreach (array_chunk($items, 25, TRUE) as $batch) {
      $entities = array_map(static fn ($item) => $item->getOriginalObject()->getValue(), $batch);

      try {
        $chunkResult = $this->textPipeline->extractChunks($entities);
      }
      catch (PipelineException) {
        // Remove all items in this batch on pipeline failure.
        foreach (array_keys($batch) as $key) {
          unset($items[$key]);
        }
        continue;
      }

      if (empty($chunkResult->textsForEmbedding)) {
        foreach (array_keys($batch) as $key) {
          unset($items[$key]);
        }
        continue;
      }

      // Track which entities got at least one result across all models.
      $entitiesWithResults = [];

      foreach ($models as $model) {
        $suffix = EmbeddingsApi::sanitizeModelName($model);
        $fieldName = 'embeddings_' . $suffix;

        try {
          $embeddings = $this->embeddingsModel->batchGetEmbedding($chunkResult->textsForEmbedding, $model);
        }
        catch (EmbeddingsModelException) {
          continue;
        }

        // Assemble results per entity.
        foreach ($chunkResult->entityChunkMap as $entityKey => $chunkKeys) {
          $entityEmbeddings = [];
          foreach ($chunkKeys as $chunkKey) {
            if (isset($embeddings[$chunkKey])) {
              $entityEmbeddings[] = [
                'vector' => $embeddings[$chunkKey],
                'content' => $chunkResult->textsForEmbedding[$chunkKey],
              ];
            }
          }

          if (!empty($entityEmbeddings)) {
            $entitiesWithResults[$entityKey] = TRUE;

            $fields = $this->getFieldsHelper()
              ->filterForPropertyPath($items[$entityKey]->getFields(), NULL, $fieldName);

            foreach ($fields as $field) {
              foreach ($entityEmbeddings as $embedding) {
                $field->addValue($embedding);
              }
            }
          }
        }
      }

      // Remove items that got zero results across ALL models.
      foreach (array_diff_key($batch, $entitiesWithResults) as $key => $item) {
        unset($items[$key]);
      }
    }
  }

}
