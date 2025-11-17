<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_react_search\Unit\EventSubscriber;

use DG\BypassFinals;
use Drupal\elasticsearch_connector\Event\FieldMappingEvent;
use Drupal\elasticsearch_connector\Event\SupportsDataTypeEvent;
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
   * The mocked fieldMappingEvent.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected ObjectProphecy $fieldMappingEvent;

  /**
   * The mocked supportsDataTypeEvent.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected ObjectProphecy $supportsDataTypeEvent;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    BypassFinals::enable();

    $this->field = $this->prophesize(Field::class);
    $this->field->getType()->willReturn('string');
    $this->field->getDataDefinition()->willReturn([]);

    $this->fieldMappingEvent = $this->prophesize(FieldMappingEvent::class);
    $this->fieldMappingEvent->getField()->willReturn($this->field->reveal());
    $this->fieldMappingEvent->setParam(Argument::any())->willReturn($this->fieldMappingEvent->reveal());

    $this->supportsDataTypeEvent = $this->prophesize(SupportsDataTypeEvent::class);
    $this->supportsDataTypeEvent->getType()->willReturn('comma_separated_string');

    $this->eventSubscriber = new SearchApiSubscriber();
  }

  /**
   * Tests the getSubscribedEvents method.
   */
  public function testGetSubscribedEvents(): void {
    $this->assertEquals([
      SearchApiEvents::MAPPING_FIELD_TYPES => 'mapFieldTypes',
      FieldMappingEvent::class => 'alterFieldMapping',
      SupportsDataTypeEvent::class => 'alterSupportsDataType',
    ], $this->eventSubscriber->getSubscribedEvents());
  }

  /**
   * Tests the alterFieldMapping method with nested properties.
   */
  public function testAlterFieldMappingNestedProperties(): void {
    $this->field->getDataDefinition()->willReturn(['nested_properties' => ['nested_property' => 'nested_property_value']]);
    $this->fieldMappingEvent->getParam()->willReturn([]);
    $this->fieldMappingEvent->setParam(['properties' => ['nested_property' => 'nested_property_value']])->shouldBeCalled();

    $this->eventSubscriber->alterFieldMapping($this->fieldMappingEvent->reveal());
  }

  /**
   * Tests the alterFieldMapping method exception handling.
   */
  public function testAlterFieldMappingExceptionHandling(): void {
    $this->field->getDataDefinition()->willThrow(new SearchApiException('Test exception'));
    $this->fieldMappingEvent->getParam()->willReturn(['properties' => ['nested_property' => 'nested_property']]);

    $this->fieldMappingEvent->setParam(Argument::any())->shouldNotBeCalled();

    $this->eventSubscriber->alterFieldMapping($this->fieldMappingEvent->reveal());
  }

  /**
   * Tests the alterFieldMapping method with empty nested properties.
   */
  public function testAlterFieldMappingEmptyNestedProperties(): void {
    $this->field->getDataDefinition()->willReturn([]);
    $this->fieldMappingEvent->getParam()->willReturn([]);

    $this->fieldMappingEvent->setParam(Argument::any())->shouldNotBeCalled();

    $this->eventSubscriber->alterFieldMapping($this->fieldMappingEvent->reveal());
  }

  /**
   * Tests the alterFieldMapping with comma separated string type.
   */
  public function testAlterFieldMappingCommaSeparatedStringType(): void {
    $this->field->getType()->willReturn('comma_separated_string');
    $this->field->getDataDefinition()->willReturn([]);
    $this->fieldMappingEvent->getParam()->willReturn([]);
    $this->fieldMappingEvent->setParam(['type' => 'keyword'])->shouldBeCalled();
    $this->eventSubscriber->alterFieldMapping($this->fieldMappingEvent->reveal());
  }

  /**
   * Tests the alterSupportsDataType method.
   */
  public function testAlterSupportsDataType(): void {
    $this->supportsDataTypeEvent->getType()->willReturn('comma_separated_string');
    $this->supportsDataTypeEvent->setIsSupported(TRUE)->shouldBeCalled();
    $this->eventSubscriber->alterSupportsDataType($this->supportsDataTypeEvent->reveal());
  }

  /**
   * Tests the alterSupportsDataType method with unsupported type.
   */
  public function testAlterSupportsDataTypeUnsupportedType(): void {
    $this->supportsDataTypeEvent->getType()->willReturn('unsupported_type');
    $this->supportsDataTypeEvent->setIsSupported(Argument::any())->shouldNotBeCalled();
    $this->eventSubscriber->alterSupportsDataType($this->supportsDataTypeEvent->reveal());
  }

}
