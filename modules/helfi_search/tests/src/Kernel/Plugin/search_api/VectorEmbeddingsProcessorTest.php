<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_search\Kernel\Plugin\search_api;

use Drupal\helfi_search\EmbeddingsModelInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Utility\Utility;
use Drupal\Tests\search_api\Kernel\Processor\ProcessorTestBase;
use PHPUnit\Framework\Attributes\Group;

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
   * Tests skip configuration.
   */
  public function testSkipConfiguration() {
    $items = $this->createNodeItems([
      [
        'title' => 'Test',
        'type' => 'test_node_bundle_1',
      ],
      [
        'title' => 'Test',
        'type' => 'test_node_bundle_2',
      ],
    ]);

    $this->processor->alterIndexedItems($items);

    // All items are removed since the text converter is not configured.
    $this->assertCount(0, $items);

    $model = $this->prophesize(EmbeddingsModelInterface::class);
    $model
      ->batchGetEmbedding([])
      ->willReturn([
          [1, 2, 3],
      ]);

    $this->container->set(EmbeddingsModelInterface::class, $model->reveal());

    $this->processor = \Drupal::getContainer()
      ->get('search_api.plugin_helper')
      ->createProcessorPlugin($this->index, 'helfi_search_embeddings');

    $items = $this->createNodeItems([
      [
        'title' => 'Test',
        'type' => 'test_node_bundle_1',
      ],
      [
        'title' => 'Test',
        'type' => 'test_node_bundle_2',
      ],
    ]);

    $this->processor->alterIndexedItems($items);

    // Embedding was generated for one item.
    $this->assertCount(1, $items);

    // Vector was assed to the fields.
    $this->assertEquals(["1", "2", "3"], array_first($items)->getFields()['embeddings']->getValues());
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
