<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\helfi_platform_config\Plugin\Field\FieldType\GeoShapeItem;
use Drupal\Tests\UnitTestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests the GeoShapeItem field type.
 *
 * @coversDefaultClass \Drupal\helfi_platform_config\Plugin\Field\FieldType\GeoShapeItem
 * @group helfi_platform_config
 */
class GeoShapeItemTest extends UnitTestCase {
  use ProphecyTrait;

  /**
   * Tests property definitions and schema.
   *
   * @covers ::propertyDefinitions
   * @covers ::schema
   */
  public function testFieldDefinitions(): void {
    $fieldStorage = $this->prophesize(FieldStorageDefinitionInterface::class)->reveal();

    // Test properties.
    $properties = GeoShapeItem::propertyDefinitions($fieldStorage);
    $this->assertArrayHasKey('value', $properties);
    $this->assertArrayHasKey('geo_shape', $properties);
    $this->assertEquals('computed_geo_shape', $properties['geo_shape']->getDataType());
    $this->assertTrue($properties['geo_shape']->isComputed());

    // Test schema.
    $schema = GeoShapeItem::schema($fieldStorage);
    $this->assertEquals('text', $schema['columns']['value']['type']);
  }

}
