<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_search\Kernel\Plugin\search_api;

use Drupal\helfi_search\EmbeddingModel;
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

    $embeddings = new Field($this->index, EmbeddingModel::DEFAULT->fieldPrefix());
    $embeddings->setPropertyPath(EmbeddingModel::DEFAULT->fieldPrefix());
    $embeddings->setType('string');
    $embeddings->setLabel('Vector embeddings (default model)');
    $this->index->addField($embeddings);
    $this->index->save();
  }

  /**
   * Tests that extraction failure halts processing.
   */
  public function testExtractionFailureThrows(): void {
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

    $this->expectException(PipelineException::class);

    $item = array_first($items);
    $this->processor->addFieldValues($item);
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
    $this->index->addProcessor($this->processor);

    $items = $this->createNodeItems([
      ['title' => 'Test', 'type' => 'test_node_bundle_1'],
    ]);

    $item = array_first($items);
    $this->processor->addFieldValues($item);

    // Item still exists, just has no embedding field values.
    $field = $item->getField(EmbeddingModel::DEFAULT->fieldPrefix());
    $this->assertEmpty($field?->getValues() ?? []);
  }

  /**
   * Tests embedding plugin.
   */
  public function testPipeline(): void {
    $first = new Chunk('Body text');
    $first->snippet = 'Intro snippet';
    $first->fragment = 'how-to-apply';

    $hidden = new Chunk('Short body');
    $hidden->snippet = 'Short snippet';
    $hidden->fragment = 'thin-section';

    $textPipeline = $this->prophesize(TextPipeline::class);
    $textPipeline->process(Argument::any())->willReturn([$first, $hidden]);
    $this->container->set(TextPipeline::class, $textPipeline->reveal());

    $this->container->set(EmbeddingsModelInterface::class, new class implements EmbeddingsModelInterface {

      /**
       * {@inheritdoc}
       */
      public function getEmbedding(string $text, EmbeddingModel $model): array {
        return [0.1, 0.2, 0.3];
      }

      /**
       * {@inheritdoc}
       */
      public function batchGetEmbedding(array $batch, EmbeddingModel $model): array {
        // Distinct vector per chunk so we can assert each keeps its own.
        return array_map(static fn (string $text) => [(float) mb_strlen($text)], $batch);
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
    $field = $this->index->getField(EmbeddingModel::DEFAULT->fieldPrefix());
    $field->setType('embeddings');
    $item->setField(EmbeddingModel::DEFAULT->fieldPrefix(), $field);
    $this->processor->addFieldValues($item);

    $values = $item->getField(EmbeddingModel::DEFAULT->fieldPrefix())->getValues();
    $this->assertCount(2, $values);

    // First chunk: its own vector, snippet and fragment.
    $this->assertSame([(float) mb_strlen('Body text')], $values[0]['vector']);
    $this->assertSame('Intro snippet', $values[0]['content']);
    $this->assertSame('how-to-apply', $values[0]['fragment']);

    // Hidden chunk: its own vector, but the first chunk's snippet and fragment.
    $this->assertSame([(float) mb_strlen('Short body')], $values[1]['vector']);
    $this->assertSame('Intro snippet', $values[1]['content']);
    $this->assertSame('how-to-apply', $values[1]['fragment']);
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
