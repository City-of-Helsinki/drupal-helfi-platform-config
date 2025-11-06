<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_react_search\Kernel\Plugin\SearchApi\Processor;

use Drupal\helfi_tpr\Entity\Unit;
use Drupal\helfi_tpr\Entity\Service;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Utility\Utility;

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
    ]);
    $unit->save();

  }

  /**
   * Tests that field values are added correctly.
   *
   * @covers ::addFieldValues
   */
  public function testAddFieldValues() : void {
    // Extract field values and check the values.
    $id = Utility::createCombinedId('entity:tpr_service', $this->services[0]->id() . ':en');
    $item = \Drupal::getContainer()
      ->get('search_api.fields_helper')
      ->createItemFromObject($this->index, $this->services[0]->getTypedData(), $id);

    $fields = $item->getFields();
    $this->assertEquals([
      [
        [
          'name' => 'Test unit',
          'name_override' => 'Test unit override',
          'address' => [
            'address_line1' => 'address line 1',
            'address_line2' => 'address line 2',
            'postal_code' => '00100',
            'locality' => 'Test city',
            'country_code' => 'FI',
          ],
          'location' => [
            'lat' => 123,
            'lon' => 321,
          ],
        ],
      ],
    ], $fields['units']->getValues());
  }

}
