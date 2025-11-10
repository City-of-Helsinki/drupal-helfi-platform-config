<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_react_search\Kernel\Plugin\SearchApi\Processor;

use Drupal\helfi_tpr_config\Entity\Unit;
use Drupal\helfi_tpr\Entity\Service;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\image\Entity\ImageStyle;
use Drupal\search_api\Utility\Utility;
use Drupal\search_api\Item\Field;

/**
 * Tests the units for service processor.
 *
 * @group helfi_react_search
 * @coversDefaultClass \Drupal\helfi_react_search\Plugin\search_api\processor\UnitsForService
 */
class UnitsForServiceTest extends ServiceProcessorTestBase {

  /**
   * Test service entities.
   *
   * @var \Drupal\helfi_tpr\Entity\Service[]
   */
  protected $services;

  /**
   * {@inheritdoc}
   */
  public function setUp($processor = NULL): void {
    parent::setUp('units_for_service');

    $this->installEntitySchema('tpr_unit');
    $this->installEntitySchema('image_style');
    $this->installEntitySchema('media');
    $this->installEntitySchema('media_type');
    $this->installEntitySchema('file');

    $this->installSchema('file', 'file_usage');

    $searchApiField = new Field($this->index, 'units');
    $searchApiField->setType('object');
    $searchApiField->setPropertyPath('units_for_service');
    $searchApiField->setLabel('Units for service');

    $this->index->addField($searchApiField);
    $this->index->setOption('index_directly', TRUE);
    $this->index->save();

    $this->services[0] = Service::create([
      'name' => $this->randomString(),
      'id' => $this->generateRandomEntityId(),
    ]);
    $this->services[0]->save();

    $imageStyle = ImageStyle::create([
      'label' => 'Test image style',
      'name' => '1.5_1022w_682h_LQ',
    ]);
    $imageStyle->save();

    $image = File::create([
      'name' => 'Test image',
      'id' => $this->generateRandomEntityId(),
      'uri' => 'public://test.jpg',
      'filemime' => 'image/jpeg',
      'filesize' => 100,
      'status' => TRUE,
      'uid' => 1,
      'created' => time(),
      'changed' => time(),
    ]);
    $image->save();

    $mediaType = MediaType::create([
      'name' => 'Image',
      'id' => 'image',
      'source' => 'image',
      'source_configuration' => [
        'source_field' => 'field_media_image',
      ],
    ]);
    $mediaType->save();

    $imageField = FieldStorageConfig::create([
      'field_name' => 'field_media_image',
      'entity_type' => 'media',
      'type' => 'image',
    ]);
    $imageField->save();

    $imageFieldInstance = FieldConfig::create([
      'field_name' => 'field_media_image',
      'entity_type' => 'media',
      'bundle' => 'image',
    ]);
    $imageFieldInstance->save();

    $photographerField = FieldStorageConfig::create([
      'field_name' => 'field_photographer',
      'entity_type' => 'media',
      'type' => 'string',
    ]);
    $photographerField->save();

    $photographerFieldInstance = FieldConfig::create([
      'field_name' => 'field_photographer',
      'entity_type' => 'media',
      'bundle' => 'image',
    ]);
    $photographerFieldInstance->save();

    $media = Media::create([
      'name' => 'Test media',
      'bundle' => 'image',
      'id' => $this->generateRandomEntityId(),
      'field_media_image' => [
        ['entity' => $image],
      ],
      'field_photographer' => [
        ['value' => 'Test photographer'],
      ],
    ]);
    $media->save();

    $unit = Unit::create([
      'name' => 'Test unit',
      'name_override' => 'Test unit override',
      'id' => $this->generateRandomEntityId(),
      'services' => [
        ['entity' => $this->services[0]],
      ],
      'latitude' => 123,
      'longitude' => 321,
      'address' => [
        'address_line1' => 'address line 1',
        'address_line2' => 'address line 2',
        'address_line3' => 'address line 3, should not be indexed',
        'postal_code' => '00100',
        'locality' => 'Test city',
        'country_code' => 'FI',
      ],
      'picture_url_override' => [
        ['entity' => $media],
      ],
    ]);
    $unit->save();

  }

  /**
   * Tests that field values are added correctly.
   *
   * @covers ::addFieldValues
   * @covers ::getImageValue
   * @covers ::getAddressValue
   */
  public function testAddFieldValues() : void {
    // Extract field values and check the values.
    $id = Utility::createCombinedId('entity:tpr_service', $this->services[0]->id() . ':en');
    $item = \Drupal::getContainer()
      ->get('search_api.fields_helper')
      ->createItemFromObject($this->index, $this->services[0]->getTypedData(), $id);

    $fields = $item->getFields();
    $values = $fields['units']->getValues();
    $this->assertEquals('Test unit', $values[0][0]['name']);
    $this->assertEquals('Test unit override', $values[0][0]['name_override']);
    $this->assertEquals([
      'address_line1' => 'address line 1',
      'address_line2' => 'address line 2',
      'postal_code' => '00100',
      'locality' => 'Test city',
      'country_code' => 'FI',
    ], $values[0][0]['address']);
    $this->assertEquals([
      'lat' => 123,
      'lon' => 321,
    ], $values[0][0]['location']);
    $this->assertArrayHasKey('variants', $values[0][0]['image']);
    $this->assertArrayHasKey('alt', $values[0][0]['image']);
    $this->assertArrayHasKey('photographer', $values[0][0]['image']);
    $this->assertArrayHasKey('title', $values[0][0]['image']);
    $this->assertArrayHasKey('url', $values[0][0]['image']);
  }

}
