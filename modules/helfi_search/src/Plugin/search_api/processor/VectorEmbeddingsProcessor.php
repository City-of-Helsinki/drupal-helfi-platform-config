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
   * All supported models that have fields in the index.
   *
   * Hardcoded because removing or renaming fields breaks search_api.
   * To enable/disable indexing for a model, use the 'openai_models' config.
   */
  private const array SUPPORTED_MODELS = [
    'text-embedding-3-small',
    'text-embedding-3-large',
  ];

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
    return $this->configFactory
      ->get('helfi_search.settings')
      ->get('openai_models') ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL): array {
    $properties = [];

    if (!$datasource) {
      foreach (self::SUPPORTED_MODELS as $model) {
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
   */
  public function addFieldValues(ItemInterface $item): void {
    $models = $this->getModels();

    if (empty($models)) {
      return;
    }

    $entity = $item->getOriginalObject()->getValue();

    try {
      $chunks = $this->textPipeline->process($entity);
    }
    catch (PipelineException) {
      return;
    }

    if (empty($chunks)) {
      return;
    }

    foreach ($models as $model) {
      try {
        $vectors = $this->embeddingsModel->batchGetEmbedding($chunks, $model);
      }
      catch (EmbeddingsModelException) {
        continue;
      }

      $suffix = EmbeddingsApi::sanitizeModelName($model);
      $fieldName = 'embeddings_' . $suffix;

      $fields = $this->getFieldsHelper()
        ->filterForPropertyPath($item->getFields(FALSE), NULL, $fieldName);

      foreach ($vectors as $index => $vector) {
        foreach ($fields as $field) {
          $field->addValue([
            'vector' => $vector,
            'content' => $chunks[$index],
          ]);
        }
      }
    }
  }

}
