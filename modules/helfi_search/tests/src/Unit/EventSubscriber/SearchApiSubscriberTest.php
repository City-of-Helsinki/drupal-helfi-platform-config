<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_search\Unit\EventSubscriber;

use Drupal\elasticsearch_connector\Event\IndexPreCreateEvent;
use Drupal\helfi_search\EventSubscriber\SearchApiSubscriber;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\Field;
use Drupal\Tests\UnitTestCase;
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
   * Builds an IndexPreCreateEvent for an index with the given fields.
   *
   * @param array<string, string> $fields
   *   Map of Search API field ID => field data type.
   * @param array<mixed> $params
   *   Initial index creation params.
   */
  private function preCreateEvent(array $fields, array $params = ['index' => 'embeddings']): IndexPreCreateEvent {
    $mocks = [];
    foreach ($fields as $fieldId => $type) {
      $field = $this->prophesize(Field::class);
      $field->getType()->willReturn($type);
      $mocks[$fieldId] = $field->reveal();
    }

    $index = $this->prophesize(IndexInterface::class);
    $index->getFields()->willReturn($mocks);

    return new IndexPreCreateEvent($params, $index->reveal());
  }

}
