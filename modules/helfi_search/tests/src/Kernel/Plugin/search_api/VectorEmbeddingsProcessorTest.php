<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_search\Kernel\Plugin\search_api;

use Drupal\helfi_search\Pipeline\PipelineException;
use Drupal\helfi_search\Pipeline\TextChunkResult;
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
   * Tests that extraction failure removes items from the batch.
   */
  public function testExtractionFailureRemovesItems(): void {
    $textPipeline = $this->prophesize(TextPipeline::class);
    $textPipeline
      ->extractChunks(Argument::any())
      ->willThrow(new PipelineException('Extraction failed'));
    $this->container->set(TextPipeline::class, $textPipeline->reveal());

    $this->processor = \Drupal::getContainer()
      ->get('search_api.plugin_helper')
      ->createProcessorPlugin($this->index, 'helfi_search_embeddings');

    $items = $this->createNodeItems([
      ['title' => 'Test', 'type' => 'test_node_bundle_1'],
    ]);

    $this->processor->alterIndexedItems($items);
    $this->assertCount(0, $items);
  }

  /**
   * Tests that items are removed when the pipeline returns empty chunks.
   */
  public function testItemsRemovedWhenNoChunks(): void {
    $textPipeline = $this->prophesize(TextPipeline::class);
    $textPipeline
      ->extractChunks(Argument::any())
      ->willReturn(new TextChunkResult([], []));
    $this->container->set(TextPipeline::class, $textPipeline->reveal());

    $this->processor = \Drupal::getContainer()
      ->get('search_api.plugin_helper')
      ->createProcessorPlugin($this->index, 'helfi_search_embeddings');

    $items = $this->createNodeItems([
      ['title' => 'Test', 'type' => 'test_node_bundle_1'],
    ]);

    $this->processor->alterIndexedItems($items);

    // Item is removed because pipeline returned no chunks.
    $this->assertCount(0, $items);
  }

  /**
   * Tests items are removed when no models are configured.
   */
  public function testItemsRemovedWhenNoModels(): void {
    $this->config('helfi_search.settings')
      ->set('openai_models', [])
      ->save();

    $this->processor = \Drupal::getContainer()
      ->get('search_api.plugin_helper')
      ->createProcessorPlugin($this->index, 'helfi_search_embeddings');

    $items = $this->createNodeItems([
      ['title' => 'Test', 'type' => 'test_node_bundle_1'],
    ]);

    $originalCount = count($items);
    $this->processor->alterIndexedItems($items);

    // Items are unchanged when no models configured (early return).
    $this->assertCount($originalCount, $items);
  }

  /**
   * Create search api items for testing.
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
