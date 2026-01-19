<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Plugin\search_api\processor;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_platform_config\TextConverter\TextConverterManager;
use Drupal\helfi_search\EmbeddingsModelInterface;
use Drupal\helfi_search\MissingConfigurationException;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Plugin\PluginFormTrait;
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
final class VectorEmbeddingsProcessor extends ProcessorPluginBase implements PluginFormInterface {

  use PluginFormTrait;

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
  public function defaultConfiguration(): array {
    return [
      'skip_embeddings_bundles' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $options = [];
    foreach ($this->index->getDatasources() as $datasource_id => $datasource) {
      foreach ($datasource->getBundles() as $bundle => $bundle_label) {
        $options["$datasource_id:$bundle"] = $datasource->label() . ' » ' . $bundle_label;
      }
    }

    $form['skip_embeddings_bundles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Index without embeddings'),
      '#description' => $this->t('Selected bundles will be indexed without vector embeddings. Items of these types will not be removed if embedding generation fails.'),
      '#options' => $options,
      '#default_value' => $this->configuration['skip_embeddings_bundles'],
    ];

    return $form;
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
    $textConversion = [];
    $skipEmbeddings = [];

    foreach ($batch as $key => $item) {
      if ($this->shouldSkipEmbeddings($item)) {
        $skipEmbeddings[$key] = TRUE;
        continue;
      }

      try {
        /** @var \Drupal\Core\Entity\EntityInterface $entity */
        $entity = $item->getOriginalObject()->getValue();

        if ($text = $this->textConverterManager->convert($entity)) {
          $textConversion[$key] = $text;
        }
      }
      catch (SearchApiException) {
        continue;
      }
    }

    try {
      $results = $this->embeddingModel->batchGetEmbedding($textConversion);
    }
    catch (MissingConfigurationException) {
      // Skips all items.
      $results = [];
    }

    foreach ($results as $key => $vector) {
      if (!$item = $items[$key]) {
        throw new \LogicException("Item should exists");
      }

      $fields = $this->getFieldsHelper()
        ->filterForPropertyPath($item->getFields(), NULL, 'embeddings');

      foreach ($fields as $field) {
        array_map(fn(mixed $value) => $field->addValue($value), $vector);
      }
    }

    // Skip items that did not produce any results, except those configured
    // to be indexed without embeddings.
    foreach (array_diff_key($batch, $results, $skipEmbeddings) as $key => $item) {
      unset($items[$key]);
    }
  }

  /**
   * Check if an item should skip embedding generation.
   *
   * @param \Drupal\search_api\Item\ItemInterface $item
   *   The search item.
   *
   * @return bool
   *   TRUE if embeddings should be skipped for this item.
   */
  private function shouldSkipEmbeddings(ItemInterface $item): bool {
    $datasource_id = $item->getDatasourceId();
    $bundle = $item->getDatasource()->getItemBundle($item->getOriginalObject());
    $key = "$datasource_id:$bundle";

    return !empty($this->configuration['skip_embeddings_bundles'][$key]);
  }

}
