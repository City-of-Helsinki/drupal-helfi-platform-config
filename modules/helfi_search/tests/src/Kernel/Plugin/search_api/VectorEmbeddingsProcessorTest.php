<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_search\Kernel\Plugin\search_api;

use Drupal\helfi_search\Pipeline\TextPipeline;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Utility\Utility;
use Drupal\Tests\search_api\Kernel\Processor\ProcessorTestBase;
use PHPUnit\Framework\Attributes\Group;
use Prophecy\Argument;

/**
 * Tests for search api plugin.
 */
#[Group('helfi_search')]
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

    $embeddings = new Field($this->index, 'embeddings');
    $embeddings->setPropertyPath('embeddings');
    $embeddings->setType('string');
    $embeddings->setLabel('Vector embeddings');
    $this->index->addField($embeddings);
    $this->index->save();
  }

  /**
   * Tests that items are removed when the pipeline returns no results.
   *
   * In a kernel test there is no HTTP server, so the real TextPipeline fails
   * during HTML extraction and processEntities returns an empty array.
   */
  public function testItemsRemovedWhenExtractionFails(): void {
    $items = $this->createNodeItems([
      ['title' => 'Test', 'type' => 'test_node_bundle_1'],
      ['title' => 'Test', 'type' => 'test_node_bundle_2'],
    ]);

    $this->processor->alterIndexedItems($items);

    // All items are removed since the pipeline fails (no HTTP server).
    $this->assertCount(0, $items);
  }

  /**
   * Tests that embeddings are generated when the pipeline succeeds.
   */
  public function testPipelineGeneratesEmbeddings(): void {
    // Mock TextPipeline to return vectors directly.
    $textPipeline = $this->prophesize(TextPipeline::class);
    $textPipeline
      ->processEntities(Argument::any())
      ->will(function (array $args): array {
        // Return a single [1.0, 2.0, 3.0] vector for every entity key.
        return array_map(static fn() => [[1.0, 2.0, 3.0]], $args[0]);
      });
    $this->container->set(TextPipeline::class, $textPipeline->reveal());

    $this->processor = \Drupal::getContainer()
      ->get('search_api.plugin_helper')
      ->createProcessorPlugin($this->index, 'helfi_search_embeddings');

    $items = $this->createNodeItems([
      ['title' => 'Test', 'type' => 'test_node_bundle_1'],
      ['title' => 'Test', 'type' => 'test_node_bundle_2'],
    ]);

    $this->processor->alterIndexedItems($items);

    // Both items received embeddings and are kept.
    $this->assertCount(2, $items);

    // The embeddings field of the first item is populated.
    $firstItem = array_first($items);
    $this->assertNotEmpty($firstItem->getFields()['embeddings']->getValues());
  }

  /**
   * Tests that items are removed when the pipeline returns empty results.
   *
   * When the embeddings API is not configured, processEntities returns an
   * empty array and all items should be removed.
   */
  public function testItemsRemovedWhenModelNotConfigured(): void {
    // Mock TextPipeline to return empty (simulates API config failure).
    $textPipeline = $this->prophesize(TextPipeline::class);
    $textPipeline
      ->processEntities(Argument::any())
      ->willReturn([]);
    $this->container->set(TextPipeline::class, $textPipeline->reveal());

    $this->processor = \Drupal::getContainer()
      ->get('search_api.plugin_helper')
      ->createProcessorPlugin($this->index, 'helfi_search_embeddings');

    $items = $this->createNodeItems([
      ['title' => 'Test', 'type' => 'test_node_bundle_1'],
    ]);

    $this->processor->alterIndexedItems($items);

    // Item is removed because pipeline returned no results.
    $this->assertCount(0, $items);
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
