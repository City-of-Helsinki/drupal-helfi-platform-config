<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\EventSubscriber;

use Drupal\elasticsearch_connector\Event\BaseParamsEvent;
use Drupal\elasticsearch_connector\Event\DeleteParamsEvent;
use Drupal\elasticsearch_connector\Event\IndexParamsEvent;
use Drupal\helfi_api_base\Cache\CacheTagInvalidatorInterface;
use Drupal\helfi_platform_config\EventSubscriber\ElasticsearchParamsSubscriber;
use Drupal\helfi_platform_config\MultisiteSearch;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;

/**
 * Tests the Elasticsearch Params EventSubscriber.
 */
#[CoversClass(ElasticsearchParamsSubscriber::class)]
#[Group('helfi_platform_config')]
class ElasticsearchParamsSubscriberTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * The MultisiteSearch.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy<\Drupal\helfi_platform_config\MultisiteSearch>
   */
  protected ObjectProphecy $multisiteSearch;

  /**
   * The cache tag invalidator.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy<\Drupal\helfi_api_base\Cache\CacheTagInvalidatorInterface>
   */
  protected ObjectProphecy $cacheTagInvalidator;

  /**
   * The EventSubscriber to test.
   *
   * @var \Drupal\helfi_platform_config\EventSubscriber\ElasticsearchParamsSubscriber
   */
  protected ElasticsearchParamsSubscriber $eventSubscriber;

  /**
   * Params used as the default event body.
   *
   * @var array<string, mixed>
   */
  protected array $params;

  /**
   * The expected params after ids are prefixed.
   *
   * @var array<string, mixed>
   */
  protected array $expectedParams;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->params = [
      'body' => [
        [
          'index' => [
            '_id' => 'item_1_to_index',
          ],
        ],
        [
          'delete' => [
            '_id' => 'item_1_to_delete',
          ],
        ],
      ],
    ];
    $this->expectedParams = [
      'body' => [
        [
          'index' => [
            '_id' => 'has_prefix_item_1_to_index',
          ],
        ],
        [
          'delete' => [
            '_id' => 'has_prefix_item_1_to_delete',
          ],
        ],
      ],
    ];

    $this->multisiteSearch = $this->prophesize(MultisiteSearch::class);
    $this->multisiteSearch->addPrefixToId('item_1_to_index')->willReturn('has_prefix_item_1_to_index');
    $this->multisiteSearch->addPrefixToId('item_1_to_delete')->willReturn('has_prefix_item_1_to_delete');

    $this->cacheTagInvalidator = $this->prophesize(CacheTagInvalidatorInterface::class);

    $this->eventSubscriber = new ElasticsearchParamsSubscriber(
      $this->multisiteSearch->reveal(),
      $this->cacheTagInvalidator->reveal(),
    );
  }

  /**
   * Tests the getSubscribedEvents method.
   */
  public function testGetSubscribedEvents(): void {
    $this->assertEquals([
      IndexParamsEvent::class => [
        ['prefixItemIds', 1],
        ['invalidateCacheTags', 0],
      ],
      DeleteParamsEvent::class => [
        ['prefixItemIds', 1],
        ['invalidateCacheTags', 0],
      ],
    ], $this->eventSubscriber->getSubscribedEvents());
  }

  /**
   * Tests the prefixItemIds method when index is multisite.
   */
  public function testPrefixItemIdsWhenIndexIsMultisite(): void {
    $this->multisiteSearch->isMultisiteIndex('test_index')->willReturn(TRUE);
    $event = $this->createEvent('test_index', $this->params);

    $this->eventSubscriber->prefixItemIds($event);

    $this->assertSame($this->expectedParams, $event->getParams());
  }

  /**
   * Tests the prefixItemIds method when index is not multisite.
   */
  public function testPrefixItemIdsWhenIndexIsNotMultisite(): void {
    $this->multisiteSearch->isMultisiteIndex('test_index')->willReturn(FALSE);
    $event = $this->createEvent('test_index', $this->params);

    $this->eventSubscriber->prefixItemIds($event);

    $this->assertSame($this->params, $event->getParams());
  }

  /**
   * Tests that embeddings index and delete ids invalidate cache tags.
   */
  public function testInvalidateCacheTagsOnEmbeddingsIndex(): void {
    $this->cacheTagInvalidator->invalidateTags([
      'helfi_multisite_content:has_prefix_item_1_to_index',
      'helfi_multisite_content:has_prefix_item_1_to_delete',
    ])->shouldBeCalled();

    $this->eventSubscriber->invalidateCacheTags(
      $this->createEvent('embeddings', $this->expectedParams),
    );
  }

  /**
   * Tests that other indexes do not invalidate cache tags.
   */
  public function testDoesNotInvalidateCacheTagsOnOtherIndexes(): void {
    $this->cacheTagInvalidator->invalidateTags(Argument::any())->shouldNotBeCalled();

    $this->eventSubscriber->invalidateCacheTags(
      $this->createEvent('hyte', $this->expectedParams),
    );
  }

  /**
   * Tests that an empty body does not invalidate cache tags.
   */
  public function testDoesNotInvalidateCacheTagsWhenBodyIsEmpty(): void {
    $this->cacheTagInvalidator->invalidateTags(Argument::any())->shouldNotBeCalled();

    $this->eventSubscriber->invalidateCacheTags(
      $this->createEvent('embeddings', ['body' => []]),
    );
  }

  /**
   * Tests that document source rows without ids do not invalidate cache tags.
   */
  public function testDoesNotInvalidateCacheTagsWhenBodyHasNoIds(): void {
    $this->cacheTagInvalidator->invalidateTags(Argument::any())->shouldNotBeCalled();

    $this->eventSubscriber->invalidateCacheTags(
      $this->createEvent('embeddings', [
        'body' => [
          ['search_api_id' => ['entity:node/1:en']],
        ],
      ]),
    );
  }

  /**
   * Creates a params event.
   *
   * @param string $index
   *   The index name.
   * @param array<string, mixed> $params
   *   The bulk params.
   */
  private function createEvent(string $index, array $params): BaseParamsEvent {
    return new IndexParamsEvent($index, $params, $index);
  }

}
