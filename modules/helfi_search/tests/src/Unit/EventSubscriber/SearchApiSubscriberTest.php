<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_search\Unit\EventSubscriber;

use Drupal\elasticsearch_connector\Event\FieldMappingEvent;
use Drupal\elasticsearch_connector\Event\IndexPreCreateEvent;
use Drupal\helfi_search\EmbeddingModel;
use Drupal\helfi_search\EventSubscriber\SearchApiSubscriber;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Processor\ProcessorProperty;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for the helfi_search search api event subscriber.
 */
#[Group('helfi_search')]
class SearchApiSubscriberTest extends UnitTestCase {

  /**
   * Tests that every embeddings field has its vector excluded.
   */
  public function testExcludeVectorsFromSource(): void {
    $subscriber = new SearchApiSubscriber();

    $event = $this->preCreateEvent([
      'embeddings_text_embedding_3_large' => 'embeddings',
      'embeddings_text_embedding_3_small' => 'embeddings',
      'label' => 'string',
      'published_at' => 'date',
    ]);

    $subscriber->excludeVectorsFromSource($event);

    $this->assertSame([
      'embeddings_text_embedding_3_large.vector',
      'embeddings_text_embedding_3_small.vector',
    ], $event->getParams()['body']['mappings']['_source']['excludes']);
  }

  /**
   * A dense_vector mapping always includes the dims parameter.
   */
  #[DataProvider('embeddingModelProvider')]
  public function testDenseVectorMappingIncludesDims(EmbeddingModel $model): void {
    $index = $this->prophesize(IndexInterface::class)->reveal();
    $field = (new Field($index, $model->fieldPrefix()))
      ->setType('embeddings')
      ->setPropertyPath($model->fieldPrefix())
      ->setDataDefinition(new ProcessorProperty([
        'type' => 'embeddings',
        'settings' => [
          'embeddings_model_dimensions' => $model->dimensions(),
        ],
      ]));

    $event = new FieldMappingEvent($field, []);
    (new SearchApiSubscriber())->mapElasticFields($event);

    $vector = $event->getParam()['properties']['vector'];
    $this->assertSame('dense_vector', $vector['type']);
    $this->assertArrayHasKey('dims', $vector);
    $this->assertSame($model->dimensions(), $vector['dims']);
  }

  /**
   * Data provider for testDenseVectorMappingIncludesDims().
   *
   * @return array<string, array{EmbeddingModel}>
   *   Embedding model cases.
   */
  public static function embeddingModelProvider(): array {
    $cases = [];
    foreach (EmbeddingModel::cases() as $model) {
      $cases[$model->value] = [$model];
    }
    return $cases;
  }

  /**
   * Builds an IndexPreCreateEvent for an index with the given fields.
   *
   * @param array<string, string> $fields
   *   Map of Search API field ID => field data type.
   * @param array<mixed> $params
   *   Initial index creation params.
   */
  private function preCreateEvent(array $fields, array $params = ['index' => 'embeddings']): IndexPreCreateEvent {
    $fieldIndex = $this->prophesize(IndexInterface::class)->reveal();
    $indexFields = [];
    foreach ($fields as $fieldId => $type) {
      $indexFields[$fieldId] = (new Field($fieldIndex, $fieldId))->setType($type);
    }

    $index = $this->prophesize(IndexInterface::class);
    $index->getFields()->willReturn($indexFields);

    return new IndexPreCreateEvent($params, $index->reveal());
  }

}
