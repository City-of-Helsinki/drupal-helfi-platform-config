<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Plugin\search_api\processor;

use Drupal\Core\StringTranslation\TranslatableMarkup;
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
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $processor = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $processor->textPipeline = $container->get(TextPipeline::class);
    return $processor;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL): array {
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
   * Process items in batches of 25 through the text-to-vector pipeline.
   */
  public function alterIndexedItems(array &$items): void {
    foreach (array_chunk($items, 25, TRUE) as $batch) {
      $entities = array_map(static fn ($item) => $item->getOriginalObject()->getValue(), $batch);

      $results = $this->textPipeline->processEntities($entities);

      // Assign embeddings to items that got results.
      foreach ($results as $key => $embeddings) {
        $fields = $this->getFieldsHelper()
          ->filterForPropertyPath($items[$key]->getFields(), NULL, 'embeddings');

        foreach ($fields as $field) {
          foreach ($embeddings as $embedding) {
            $field->addValue($embedding);
          }
        }
      }

      // Remove items that produce no results.
      foreach (array_diff_key($batch, $results) as $key => $item) {
        unset($items[$key]);
      }
    }
  }

}
