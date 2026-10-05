<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_recommendations\Unit\EventSubscriber;

use Drupal\elasticsearch_connector\Event\BaseParamsEvent;
use Drupal\elasticsearch_connector\Event\DeleteParamsEvent;
use Drupal\elasticsearch_connector\Event\IndexParamsEvent;
use Drupal\helfi_recommendations\EventSubscriber\ElasticsearchParamsSubscriber;
use Drupal\helfi_recommendations\RecommendationManagerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests the Elasticsearch params subscriber.
 */
#[Group('helfi_recommendations')]
#[CoversClass(ElasticsearchParamsSubscriber::class)]
class ElasticsearchParamsSubscriberTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * A valid lowercase UUID.
   */
  private const string VALID_UUID = '550e8400-e29b-41d4-a716-446655440000';

  /**
   * Another valid lowercase UUID.
   */
  private const string OTHER_UUID = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';

  /**
   * Tests the subscribed events.
   */
  public function testGetSubscribedEvents(): void {
    $this->assertSame([
      IndexParamsEvent::class => 'invalidateCacheTags',
      DeleteParamsEvent::class => 'invalidateCacheTags',
    ], ElasticsearchParamsSubscriber::getSubscribedEvents());
  }

  /**
   * Tests cache tag invalidation for bulk param bodies.
   *
   * @param class-string<\Drupal\elasticsearch_connector\Event\BaseParamsEvent> $event_class
   *   The event class to dispatch.
   * @param string $index_name
   *   The Elasticsearch index name.
   * @param array<int, array<mixed>> $body
   *   The bulk request body.
   * @param array<int, string>|null $expected_uuids
   *   UUIDs passed to the recommendation manager, or NULL when it must not be
   *   called.
   */
  #[DataProvider('invalidateCacheTagsProvider')]
  public function testInvalidateCacheTags(string $event_class, string $index_name, array $body, ?array $expected_uuids): void {
    $manager = $this->prophesize(RecommendationManagerInterface::class);
    if ($expected_uuids === NULL) {
      $manager->invalidateExternalCacheTags(Argument::any())->shouldNotBeCalled();
    }
    else {
      $manager->invalidateExternalCacheTags($expected_uuids)->shouldBeCalledOnce();
    }

    $subscriber = new ElasticsearchParamsSubscriber($manager->reveal());
    $subscriber->invalidateCacheTags($this->createEvent($event_class, $index_name, $body));
  }

  /**
   * Provides bulk bodies for cache tag invalidation.
   *
   * @return \Generator
   *   Test cases.
   */
  public static function invalidateCacheTagsProvider(): \Generator {
    $valid_id = 'entity:suggested_topics/' . self::VALID_UUID . ':en';
    $other_id = 'entity:suggested_topics/' . self::OTHER_UUID . ':sv';

    yield 'index action with a valid uuid ignores the document source' => [
      IndexParamsEvent::class,
      'suggestions',
      [
        ['index' => ['_id' => $valid_id, '_index' => 'suggestions']],
        ['uuid' => [self::VALID_UUID], 'title' => ['ignored']],
      ],
      [self::VALID_UUID],
    ];

    yield 'delete action with a valid uuid' => [
      DeleteParamsEvent::class,
      'suggestions',
      [
        ['delete' => ['_id' => $valid_id, '_index' => 'suggestions']],
      ],
      [self::VALID_UUID],
    ];

    yield 'collects valid ids and skips invalid or unrelated ones' => [
      IndexParamsEvent::class,
      'suggestions',
      [
        ['index' => ['_id' => $valid_id]],
        ['delete' => ['_id' => 'entity:suggested_topics/not-a-uuid:en']],
        ['delete' => ['_id' => 'entity:node/1:en']],
        ['index' => ['_id' => $other_id]],
        ['delete' => ['_id' => '']],
        ['index' => []],
        ['unrelated' => TRUE],
      ],
      [self::VALID_UUID, self::OTHER_UUID],
    ];

    yield 'ignores an uppercase uuid' => [
      DeleteParamsEvent::class,
      'suggestions',
      [
        ['delete' => ['_id' => 'entity:suggested_topics/' . strtoupper(self::VALID_UUID) . ':en']],
      ],
      NULL,
    ];

    yield 'ignores another index' => [
      IndexParamsEvent::class,
      'other',
      [
        ['index' => ['_id' => $valid_id]],
      ],
      NULL,
    ];

    yield 'ignores an empty body' => [
      DeleteParamsEvent::class,
      'suggestions',
      [],
      NULL,
    ];

    yield 'ignores a body without item ids' => [
      IndexParamsEvent::class,
      'suggestions',
      [
        ['uuid' => [self::VALID_UUID]],
      ],
      NULL,
    ];
  }

  /**
   * Creates an index or delete params event.
   *
   * @param class-string<\Drupal\elasticsearch_connector\Event\BaseParamsEvent> $event_class
   *   The event class.
   * @param string $index_name
   *   The Elasticsearch index name.
   * @param array<int, array<mixed>> $body
   *   The bulk request body.
   *
   * @return \Drupal\elasticsearch_connector\Event\BaseParamsEvent
   *   The event.
   */
  private function createEvent(string $event_class, string $index_name, array $body): BaseParamsEvent {
    $params = ['body' => $body];
    if ($event_class === DeleteParamsEvent::class) {
      return new DeleteParamsEvent($index_name, $params);
    }
    return new IndexParamsEvent($index_name, $params, $index_name);
  }

}
