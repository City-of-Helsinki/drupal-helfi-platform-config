<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\helfi_platform_config\Plugin\Field\FieldType\LocationItem;
use Drupal\Tests\UnitTestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests the LocationItem field type.
 *
 * Test coverage includes:
 * - Property definitions for latitude, longitude, and computed value fields
 * - Data type validation for each field property
 * - Database schema structure for field storage
 * - Column type and size specifications.
 *
 * Note: The isEmpty() method is not tested here as it requires a Drupal
 * container and would be more appropriately tested in a kernel test.
 *
 * @coversDefaultClass \Drupal\helfi_platform_config\Plugin\Field\FieldType\LocationItem
 * @group helfi_platform_config
 */
class LocationItemTest extends UnitTestCase {
  use ProphecyTrait;

  /**
   * The field storage definition.
   *
   * @var \Drupal\Core\Field\FieldStorageDefinitionInterface|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $fieldStorageDefinition;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->fieldStorageDefinition = $this->prophesize(FieldStorageDefinitionInterface::class);
  }

  /**
   * Tests property definitions for the LocationItem field type.
   *
   * Verifies that all required properties are defined with correct
   * data types and configuration settings.
   *
   * @covers ::propertyDefinitions
   */
  public function testPropertyDefinitions(): void {
    $properties = LocationItem::propertyDefinitions($this->fieldStorageDefinition->reveal());

    // Verify all required field properties exist in the definition.
    $this->assertArrayHasKey('latitude', $properties, 'Latitude property should be defined');
    $this->assertArrayHasKey('longitude', $properties, 'Longitude property should be defined');
    $this->assertArrayHasKey('value', $properties, 'Computed value property should be defined');

    // Validate data types match field requirements.
    $this->assertEquals('float', $properties['latitude']->getDataType(), 'Latitude should be defined as a float type');
    $this->assertEquals('float', $properties['longitude']->getDataType(), 'Longitude should be defined as a float type');
    $this->assertEquals('computed_location', $properties['value']->getDataType(), 'Value should be defined as a computed_location type');

    // Verify computed property attributes are correctly configured.
    $this->assertTrue($properties['value']->isComputed(), 'Value property should be marked as computed');
    $this->assertTrue($properties['value']->isReadOnly(), 'Value property should be configured as read-only');
  }

  /**
   * Tests database schema definition for the LocationItem field type.
   *
   * Verifies that the schema structure contains appropriate column
   * definitions with correct data types and sizes for storage.
   *
   * @covers ::schema
   */
  public function testSchemaDefinition(): void {
    $schema = LocationItem::schema($this->fieldStorageDefinition->reveal());

    // Verify database schema structure contains all required elements.
    $this->assertArrayHasKey('columns', $schema, 'Schema should define columns array');
    $this->assertArrayHasKey('latitude', $schema['columns'], 'Schema should include latitude column definition');
    $this->assertArrayHasKey('longitude', $schema['columns'], 'Schema should include longitude column definition');

    // Validate column specifications match database requirements.
    $this->assertEquals('float', $schema['columns']['latitude']['type'], 'Latitude column should be defined as float type');
    $this->assertEquals('normal', $schema['columns']['latitude']['size'], 'Latitude column should specify normal size');
    $this->assertEquals('float', $schema['columns']['longitude']['type'], 'Longitude column should be defined as float type');
    $this->assertEquals('normal', $schema['columns']['longitude']['size'], 'Longitude column should specify normal size');
  }

}
