<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_search\Kernel\Plugin\search_api;

use Drupal\helfi_search\EmbeddingsModelInterface;
use Drupal\helfi_search\Pipeline\Chunk;
use Drupal\helfi_search\Pipeline\PipelineException;
use Drupal\helfi_search\Pipeline\TextPipeline;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Utility\Utility;
use Drupal\Tests\search_api\Kernel\Processor\ProcessorTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Prophecy\Argument;

/**
 * Tests for search api plugin.
 */
#[Group('helfi_search')]
#[RunTestsInSeparateProcesses]
class VectorEmbeddingsProcessorTest extends ProcessorTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_platform_config',
    'config_rewrite',
    'helfi_api_base',
    'helfi_search',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp($processor = NULL): void {
    parent::setUp('helfi_search_embeddings');

    NodeType::create([
      'type' => 'test_node_bundle_1',
    ])->save();

    NodeType::create([
      'type' => 'test_node_bundle_2',
    ])->save();

    // Configure models.
    $this->config('helfi_search.settings')
      ->set('openai_models', ['text-embedding-3-small'])
      ->save();

    $embeddings = new Field($this->index, 'embeddings_text_embedding_3_small');
    $embeddings->setPropertyPath('embeddings_text_embedding_3_small');
    $embeddings->setType('string');
    $embeddings->setLabel('Vector embeddings (text-embedding-3-small)');
    $this->index->addField($embeddings);
    $this->index->save();
  }

  /**
   * Tests that extraction failure skips the item without removing it.
   */
  public function testExtractionFailureSkipsItem(): void {
    $textPipeline = $this->prophesize(TextPipeline::class);
    $textPipeline
      ->process(Argument::any())
      ->willThrow(new PipelineException('Extraction failed'));
    $this->container->set(TextPipeline::class, $textPipeline->reveal());

    $this->processor = $this->container
      ->get('search_api.plugin_helper')
      ->createProcessorPlugin($this->index, 'helfi_search_embeddings');

    $items = $this->createNodeItems([
      ['title' => 'Test', 'type' => 'test_node_bundle_1'],
    ]);

    $item = array_first($items);
    $this->processor->addFieldValues($item);

    // Item still exists, just has no embedding field values.
    $field = $item->getField('embeddings_text_embedding_3_small');
    $this->assertEmpty($field?->getValues() ?? []);
  }

  /**
   * Tests that items have no embeddings when the pipeline returns no chunks.
   */
  public function testNoEmbeddingsWhenNoChunks(): void {
    $textPipeline = $this->prophesize(TextPipeline::class);
    $textPipeline
      ->process(Argument::any())
      ->willReturn([]);
    $this->container->set(TextPipeline::class, $textPipeline->reveal());

    $this->processor = $this->container
      ->get('search_api.plugin_helper')
      ->createProcessorPlugin($this->index, 'helfi_search_embeddings');

    $items = $this->createNodeItems([
      ['title' => 'Test', 'type' => 'test_node_bundle_1'],
    ]);

    $item = array_first($items);
    $this->processor->addFieldValues($item);

    // Item still exists, just has no embedding field values.
    $field = $item->getField('embeddings_text_embedding_3_small');
    $this->assertEmpty($field?->getValues() ?? []);
  }

  /**
   * Tests embedding plugin.
   */
  public function testPipeline(): void {
    $chunk = new Chunk('Body text');
    $chunk->snippet = 'Text';

    $textPipeline = $this->prophesize(TextPipeline::class);
    $textPipeline->process(Argument::any())->willReturn([$chunk]);
    $this->container->set(TextPipeline::class, $textPipeline->reveal());

    $this->container->set(EmbeddingsModelInterface::class, new class implements EmbeddingsModelInterface {

      /**
       * {@inheritdoc}
       */
      public function getEmbedding(string $text, string $model): array {
        return [0.1, 0.2, 0.3];
      }

      /**
       * {@inheritdoc}
       */
      public function batchGetEmbedding(array $batch, string $model): array {
        return array_map(static fn () => [0.1, 0.2, 0.3], $batch);
      }

    });

    $this->processor = $this->container
      ->get('search_api.plugin_helper')
      ->createProcessorPlugin($this->index, 'helfi_search_embeddings');

    $items = $this->createNodeItems([
      ['title' => 'Test', 'type' => 'test_node_bundle_1'],
    ]);

    $item = array_first($items);

    // Attach the embedings field to the item so that getFields(FALSE) inside
    // the processor returns it.
    $field = $this->index->getField('embeddings_text_embedding_3_small');
    $field->setType('embeddings');
    $item->setField('embeddings_text_embedding_3_small', $field);
    $this->processor->addFieldValues($item);

    $field = $item->getField('embeddings_text_embedding_3_small');
    $values = $field->getValues();
    $this->assertCount(1, $values);
    $this->assertSame([0.1, 0.2, 0.3], $values[0]['vector']);
    $this->assertSame($chunk->snippet, $values[0]['content']);
  }

  /**
   * Tests no error when no models are configured.
   */
  public function testNoErrorWhenNoModels(): void {
    $this->config('helfi_search.settings')
      ->set('openai_models', [])
      ->save();

    $this->processor = \Drupal::getContainer()
      ->get('search_api.plugin_helper')
      ->createProcessorPlugin($this->index, 'helfi_search_embeddings');

    $items = $this->createNodeItems([
      ['title' => 'Test', 'type' => 'test_node_bundle_1'],
    ]);

    $item = array_first($items);
    $this->processor->addFieldValues($item);

    // No error thrown, item still exists.
    $field = $item->getField('embeddings_text_embedding_3_small');
    $this->assertEmpty($field?->getValues() ?? []);
  }

  /**
   * Create search api items for testing.
   *
   * @phpstan-param array<array<string, mixed>> $values
   * @phpstan-return \Drupal\search_api\Item\ItemInterface<mixed>[]
   */
  private function createNodeItems(array $values): array {
    $items = [];
    foreach ($values as $node) {
      $node = Node::create($node);
      $node->save();

      $id = Utility::createCombinedId('entity:node', $node->id() . ':en');
      $items[] = $this->container
        ->get('search_api.fields_helper')
        ->createItemFromObject($this->index, $node->getTypedData(), $id);
    }

    return $items;
  }

}
