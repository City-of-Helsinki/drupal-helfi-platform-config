<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Plugin\search_api\processor;

use Drupal\helfi_api_base\TextConverter\TextConverterManager;
use Drupal\helfi_search\EmbeddingsModelInterface;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Drupal\search_api\SearchApiException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a processor for vector search.
 *
 * @SearchApiProcessor(
 *   id = "helfi_search_embeddings",
 *   label = @Translation("Vector embeddings"),
 *   description = @Translation("Adds vector embeddings to index."),
 *   stages = {
 *     "add_properties" = 0,
 *     "alter_items" = 0,
 *   },
 *   locked = true,
 *   hidden = true,
 * )
 */
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
    foreach (array_chunk($items, 10, TRUE) as $batch) {
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

    foreach ($batch as $key => $item) {
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

    $results = $this->embeddingModel->batchGetEmbedding($textConversion);

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

    // Skip items that did not produce any results.
    foreach (array_diff_key($batch, $results) as $key => $item) {
      unset($items[$key]);
    }
  }

}
