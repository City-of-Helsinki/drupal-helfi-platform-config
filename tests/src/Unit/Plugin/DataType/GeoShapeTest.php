<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\Plugin\DataType;

use Drupal\helfi_platform_config\Plugin\DataType\GeoShape;
use Drupal\Tests\UnitTestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests the GeoShape data type.
 *
 * @coversDefaultClass \Drupal\helfi_platform_config\Plugin\DataType\GeoShape
 * @group helfi_platform_config
 */
class GeoShapeTest extends UnitTestCase {
  use ProphecyTrait;

  /**
   * Tests getValue() with valid GeoJSON.
   *
   * @covers ::getValue
   */
  public function testGetValueWithValidGeoJson(): void {
    $parent = new \stdClass();
    $parent->value = '{"type": "LineString", "coordinates": [[24.9384, 60.1699], [24.9400, 60.1710]]}';

    $geoShape = $this->createGeoShapeWithParent($parent);
    $result = $geoShape->getValue();

    $this->assertIsObject($result);
    $this->assertEquals('linestring', $result->type);
    $this->assertCount(2, $result->coordinates);
  }

  /**
   * Tests getValue() returns null for invalid input.
   *
   * @covers ::getValue
   */
  public function testGetValueWithInvalidInput(): void {
    $geoShape = $this->createGeoShapeWithParent((object) ['value' => '']);
    $this->assertNull($geoShape->getValue());

    $geoShape = $this->createGeoShapeWithParent((object) ['value' => 'not json']);
    $this->assertNull($geoShape->getValue());

    $geoShape = $this->createGeoShapeWithParent((object) ['value' => '{"type": "Point"}']);
    $this->assertNull($geoShape->getValue());
  }

  /**
   * Creates a GeoShape instance with a mocked parent.
   */
  protected function createGeoShapeWithParent(object $parent): GeoShape {
    $definition = $this->prophesize('Drupal\Core\TypedData\DataDefinitionInterface');
    $geoShape = new GeoShape($definition->reveal(), 'geo_shape');

    $reflection = new \ReflectionClass($geoShape);
    $parentProperty = $reflection->getProperty('parent');
    $parentProperty->setAccessible(TRUE);
    $parentProperty->setValue($geoShape, $parent);

    return $geoShape;
  }

}
