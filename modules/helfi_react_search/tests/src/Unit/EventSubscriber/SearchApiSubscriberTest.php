<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_react_search\Unit\EventSubscriber;

use DG\BypassFinals;
use Drupal\elasticsearch_connector\Event\FieldMappingEvent;
use Drupal\helfi_react_search\EventSubscriber\SearchApiSubscriber;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Event\SearchApiEvents;
use Drupal\search_api\SearchApiException;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;

/**
 * Unit tests for SearchApiSubscriber.
 */
class SearchApiSubscriberTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * The EventSubscriber to test.
   *
   * @var \Drupal\helfi_react_search\EventSubscriber\SearchApiSubscriber
   */
  protected SearchApiSubscriber $eventSubscriber;

  /**
   * The mocked field.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected ObjectProphecy $field;

  /**
   * The mocked event.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected ObjectProphecy $event;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    BypassFinals::enable();

    $this->field = $this->prophesize(Field::class);
    $this->event = $this->prophesize(FieldMappingEvent::class);
    $this->event->getField()->willReturn($this->field->reveal());
    $this->event->setParam(Argument::any())->willReturn($this->event->reveal());

    $this->eventSubscriber = new SearchApiSubscriber();
  }

  /**
   * Tests the getSubscribedEvents method.
   */
  public function testGetSubscribedEvents(): void {
    $this->assertEquals([
      SearchApiEvents::MAPPING_FIELD_TYPES => 'mapFieldTypes',
      FieldMappingEvent::class => 'alterFieldMapping',
    ], $this->eventSubscriber->getSubscribedEvents());
  }

  /**
   * Tests the alterFieldMapping method with nested properties.
   */
  public function testAlterFieldMappingNestedProperties(): void {
    $this->field->getDataDefinition()->willReturn(['nested_properties' => ['nested_property' => 'nested_property_value']]);
    $this->event->getParam()->willReturn([]);
    $this->event->setParam(['properties' => ['nested_property' => 'nested_property_value']])->shouldBeCalled();

    $this->eventSubscriber->alterFieldMapping($this->event->reveal());
  }

  /**
   * Tests the alterFieldMapping method exception handling.
   */
  public function testAlterFieldMappingExceptionHandling(): void {
    $this->field->getDataDefinition()->willThrow(new SearchApiException('Test exception'));
    $this->event->getParam()->willReturn(['properties' => ['nested_property' => 'nested_property']]);

    $this->event->setParam(Argument::any())->shouldNotBeCalled();

    $this->eventSubscriber->alterFieldMapping($this->event->reveal());
  }

  /**
   * Tests the alterFieldMapping method with empty nested properties.
   */
  public function testAlterFieldMappingEmptyNestedProperties(): void {
    $this->field->getDataDefinition()->willReturn([]);
    $this->event->getParam()->willReturn([]);

    $this->event->setParam(Argument::any())->shouldNotBeCalled();

    $this->eventSubscriber->alterFieldMapping($this->event->reveal());
  }

}
