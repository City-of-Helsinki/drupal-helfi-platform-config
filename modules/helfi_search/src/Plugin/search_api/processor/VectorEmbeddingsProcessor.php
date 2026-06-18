<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Plugin\search_api\processor;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_search\EmbeddingModel;
use Drupal\helfi_search\EmbeddingsModelInterface;
use Drupal\helfi_search\Pipeline\TextPipeline;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
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
   * {@inheritdoc}
   *
   * @phpstan-param array<string, mixed> $configuration
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $processor = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $processor->textPipeline = $container->get(TextPipeline::class);
    $processor->embeddingsModel = $container->get(EmbeddingsModelInterface::class);
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
   * {@inheritDoc}
   *
   * @phpstan-param \Drupal\search_api\Item\ItemInterface<mixed> $item
   */
  public function addFieldValues(ItemInterface $item): void {
    $entity = $item->getOriginalObject()->getValue();

    // Throw if processing fails. This will interrupt search api. This
    // will interrupt search api indexing until the issue is resolved.
    $chunks = $this->textPipeline->process($entity);

    if (empty($chunks)) {
      return;
    }

    $embeddingTexts = array_map('strval', $chunks);

    foreach (EmbeddingModel::ENABLED as $model) {
      // Throw if processing fails. This will interrupt search api. This
      // will interrupt search api indexing until the issue is resolved.
      $vectors = $this->embeddingsModel->batchGetEmbedding($embeddingTexts, $model);

      $fieldName = $model->fieldPrefix();

      $fields = $this->getFieldsHelper()
        ->filterForPropertyPath($item->getFields(FALSE), NULL, $fieldName);

      foreach ($vectors as $index => $vector) {
        foreach ($fields as $field) {
          $field->addValue([
            'vector' => $vector,
            'content' => Unicode::truncate($chunks[$index]->snippet ?? '', 200, TRUE, TRUE),
            'fragment' => $chunks[$index]->fragment,
          ]);
        }
      }
    }
  }

}
