<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_search\Kernel\Plugin\search_api;

use Drupal\helfi_search\DocumentState;
use Drupal\helfi_search\EmbeddingModel;
use Drupal\helfi_search\Pipeline\Chunk;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Utility\Utility;
use Drupal\Tests\helfi_search\Traits\EmbeddingStoreTrait;
use Drupal\Tests\helfi_search\Traits\AllowEmbeddingTrait;
use Drupal\Tests\search_api\Kernel\Processor\ProcessorTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for search api plugin.
 */
#[Group('helfi_search')]
#[RunTestsInSeparateProcesses]
class VectorEmbeddingsProcessorTest extends ProcessorTestBase {

  use EmbeddingStoreTrait;
  use AllowEmbeddingTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_platform_config',
    'config_rewrite',
    'diff',
    'helfi_api_base',
    'helfi_search',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp($processor = NULL): void {
    parent::setUp('helfi_search_embeddings');

    $this->installSchema('helfi_search', [
      'helfi_search_document',
      'helfi_search_chunk',
    ]);

    NodeType::create([
      'type' => 'test_node_bundle_1',
    ])->save();

    $embeddings = new Field($this->index, EmbeddingModel::DEFAULT->fieldPrefix());
    $embeddings->setPropertyPath(EmbeddingModel::DEFAULT->fieldPrefix());
    $embeddings->setType('string');
    $embeddings->setLabel('Vector embeddings (default model)');
    $this->index->addField($embeddings);
    $this->index->save();
  }

  /**
   * Tests an empty database.
   */
  public function testEmptyRun(): void {
    $item = $this->createItem();
    $entity = $item->getOriginalObject()->getValue();

    $this->processor->addFieldValues($item);

    // No vectors are generated.
    $this->assertEmpty($item->getField(EmbeddingModel::DEFAULT->fieldPrefix())?->getValues() ?? []);

    // An unknown document is created so that cron picks it up.
    $this->assertSame(DocumentState::Pending, $this->getState($entity));
  }

  /**
   * Tests addFieldValues.
   */
  public function testAddFieldValues(): void {
    $first = new Chunk('Body text');
    $first->snippet = 'Body';
    $first->fragment = 'how-to-apply';

    $second = new Chunk('More body text');
    $second->snippet = 'More';
    $second->fragment = 'requirements';

    $item = $this->createItem();
    $entity = $item->getOriginalObject()->getValue();
    $this->fillChunks($entity, EmbeddingModel::DEFAULT, [$first, $second]);

    $this->processor->addFieldValues($item);

    $values = $item->getField(EmbeddingModel::DEFAULT->fieldPrefix())->getValues();

    $this->assertCount(2, $values);
    $this->assertEqualsWithDelta([0.25, 0.5], $values[0]['vector'], 1e-6);
    $this->assertSame('Body', $values[0]['content']);
    $this->assertSame('how-to-apply', $values[0]['fragment']);
    $this->assertSame('requirements', $values[1]['fragment']);
  }

  /**
   * Creates a search api item for a node, with the embeddings field attached.
   *
   * @return \Drupal\search_api\Item\ItemInterface<mixed>
   *   The item.
   */
  private function createItem(): ItemInterface {
    $node = Node::create([
      'title' => 'Test',
      'type' => 'test_node_bundle_1',
    ]);
    $node->save();

    $item = $this->container
      ->get('search_api.fields_helper')
      ->createItemFromObject(
        $this->index,
        $node->getTypedData(),
        Utility::createCombinedId('entity:node', $node->id() . ':en'),
      );

    // Attach the embeddings field.
    $field = clone $this->index->getField(EmbeddingModel::DEFAULT->fieldPrefix());
    $field->setType('embeddings');
    $item->setField(EmbeddingModel::DEFAULT->fieldPrefix(), $field);

    return $item;
  }

}
