<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_search\Kernel\Plugin\search_api;

use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
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

    $this->processor->setConfiguration([
      'skip_embeddings_bundles' => [
        'entity:node:test_node_bundle_1' => 'entity:node:test_node_bundle_1',
      ],
    ]);

    $this->processor->alterIndexedItems($items);

    // One item is removed, and the other should be
    // skipped, since the text converter is not configured.
    $this->assertCount(1, $items);
  }

  /**
   * Create search api items for testing.
   */
  private function createNodeItems(array $values): array {
    $items = [];
    foreach ($values as $node) {
      $node = Node::create($node);
      $node->save();

      // Test field value on node.
      $id = Utility::createCombinedId('entity:node', $node->id() . ':en');
      $items[] = $this->container
        ->get('search_api.fields_helper')
        ->createItemFromObject($this->index, $node->getTypedData(), $id);
    }

    return $items;
  }

}
