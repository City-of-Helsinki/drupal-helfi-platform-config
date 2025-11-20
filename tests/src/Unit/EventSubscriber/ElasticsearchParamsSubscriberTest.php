<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\EventSubscriber;

use DG\BypassFinals;
use Drupal\elasticsearch_connector\Event\BaseParamsEvent;
use Drupal\elasticsearch_connector\Event\DeleteParamsEvent;
use Drupal\elasticsearch_connector\Event\IndexParamsEvent;
use Drupal\helfi_platform_config\EventSubscriber\ElasticsearchParamsSubscriber;
use Drupal\helfi_platform_config\MultisiteSearch;
use Drupal\Tests\UnitTestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Prophecy\Argument;

/**
 * Tests the Elasticsearch Params EventSubscriber.
 */
class ElasticsearchParamsSubscriberTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * The MultisiteSearch.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected ObjectProphecy $multisiteSearch;

  /**
   * The Event.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected ObjectProphecy $event;

  /**
   * The EventSubscriber to test.
   *
   * @var \Drupal\helfi_platform_config\EventSubscriber\ElasticsearchParamsSubscriber
   */
  protected ElasticsearchParamsSubscriber $eventSubscriber;

  /**
   * The expected params.
   *
   * @var array
   */
  protected array $expectedParams;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    BypassFinals::enable();
    parent::setUp();

    $params = [
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

    $this->event = $this->prophesize(BaseParamsEvent::class);
    $this->event->getIndexName()->willReturn('test_index');
    $this->event->getParams()->willReturn($params);

    $this->multisiteSearch = $this->prophesize(MultisiteSearch::class);
    $this->multisiteSearch->addPrefixToId('item_1_to_index')->willReturn('has_prefix_item_1_to_index');
    $this->multisiteSearch->addPrefixToId('item_1_to_delete')->willReturn('has_prefix_item_1_to_delete');

    $this->eventSubscriber = new ElasticsearchParamsSubscriber($this->multisiteSearch->reveal());
  }

  /**
   * Tests the getSubscribedEvents method.
   *
   * @covers ::getSubscribedEvents
   */
  public function testGetSubscribedEvents(): void {
    $this->assertEquals([
      IndexParamsEvent::class => 'prefixItemIds',
      DeleteParamsEvent::class => 'prefixItemIds',
    ], $this->eventSubscriber->getSubscribedEvents());
  }

  /**
   * Tests the prefixItemIds method when index is multisite.
   *
   * @covers ::prefixItemIds
   * @covers ::alterItemId
   */
  public function testPrefixItemIdsWhenIndexIsMultisite(): void {
    $this->multisiteSearch->isMultisiteIndex('test_index')->willReturn(TRUE);
    $this->event->setParams($this->expectedParams)->shouldBeCalled(1);
    $this->eventSubscriber->prefixItemIds($this->event->reveal());
  }

  /**
   * Tests the prefixItemIds method when index is not multisite.
   *
   * @covers ::prefixItemIds
   * @covers ::alterItemId
   */
  public function testPrefixItemIdsWhenIndexIsNotMultisite(): void {
    $this->multisiteSearch->isMultisiteIndex('test_index')->willReturn(FALSE);
    $this->event->setParams(Argument::any())->shouldNotBeCalled();
    $this->eventSubscriber->prefixItemIds($this->event->reveal());
  }

}
