<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\EventSubscriber;

use Drupal\elasticsearch_connector\Event\FieldMappingEvent;
use Drupal\elasticsearch_connector\Event\SupportsDataTypeEvent;
use Drupal\helfi_platform_config\EventSubscriber\SearchApiSubscriber;
use Drupal\search_api\Event\MappingFieldTypesEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Drupal\search_api\Item\FieldInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Search API event subscriber field type mapping.
 */
#[CoversClass(SearchApiSubscriber::class)]
#[Group('helfi_platform_config')]
final class SearchApiSubscriberTest extends UnitTestCase {

  /**
   * Tests that field mapping events are subscribed to.
   */
  public function testGetSubscribedEvents(): void {
    $this->assertSame([
      SearchApiEvents::MAPPING_FIELD_TYPES => 'mapFieldTypes',
      SupportsDataTypeEvent::class => 'onSupportsDataType',
      FieldMappingEvent::class => 'onFieldMapping',
    ], SearchApiSubscriber::getSubscribedEvents());
  }

  /**
   * Tests that custom field types are mapped.
   */
  public function testMapFieldTypes(): void {
    $mapping = ['string' => 'string'];
    $event = new MappingFieldTypesEvent($mapping);

    (new SearchApiSubscriber())->mapFieldTypes($event);

    $this->assertSame('location', $mapping['location']);
    $this->assertSame('geo_shape', $mapping['computed_geo_shape']);
    $this->assertSame('string', $mapping['string']);
  }

  /**
   * Tests that geo_shape is marked as a supported data type.
   */
  public function testSupportsGeoShapeDataType(): void {
    $event = new SupportsDataTypeEvent('geo_shape');

    (new SearchApiSubscriber())->onSupportsDataType($event);

    $this->assertTrue($event->isSupported());
  }

  /**
   * Tests that other data types are left unsupported.
   */
  public function testDoesNotSupportOtherDataTypes(): void {
    $event = new SupportsDataTypeEvent('string');

    (new SearchApiSubscriber())->onSupportsDataType($event);

    $this->assertFalse($event->isSupported());
  }

  /**
   * Tests that geo_shape fields are mapped to Elasticsearch geo_shape.
   */
  public function testMapsGeoShapeField(): void {
    $field = $this->createMock(FieldInterface::class);
    $field->method('getType')->willReturn('geo_shape');
    $event = new FieldMappingEvent($field, ['type' => 'text']);

    (new SearchApiSubscriber())->onFieldMapping($event);

    $this->assertSame(['type' => 'geo_shape'], $event->getParam());
  }

  /**
   * Tests that other field types keep their original mapping.
   */
  public function testDoesNotMapOtherFieldTypes(): void {
    $field = $this->createMock(FieldInterface::class);
    $field->method('getType')->willReturn('string');
    $event = new FieldMappingEvent($field, ['type' => 'keyword']);

    (new SearchApiSubscriber())->onFieldMapping($event);

    $this->assertSame(['type' => 'keyword'], $event->getParam());
  }

}
