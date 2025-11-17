<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_react_search\Kernel\Plugin\SearchApi\Processor;

use Drupal\helfi_tpr\Entity\Channel;
use Drupal\helfi_tpr\Entity\ErrandService;
use Drupal\helfi_tpr\Entity\Service;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Utility\Utility;

/**
 * Tests the channels for service processor.
 *
 * @group helfi_react_search
 * @coversDefaultClass \Drupal\helfi_react_search\Plugin\search_api\processor\ChannelsForService
 */
class ChannelsForServiceTest extends ServiceProcessorTestBase {

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
    parent::setUp('channels_for_service');

    $this->installEntitySchema('tpr_errand_service');
    $this->installEntitySchema('tpr_service_channel');

    $searchApiField = new Field($this->index, 'channels');
    $searchApiField->setType('object');
    $searchApiField->setPropertyPath('channels_for_service');
    $searchApiField->setLabel('Channels for service');

    $this->index->addField($searchApiField);
    $this->index->setOption('index_directly', TRUE);
    $this->index->save();

    $channel = Channel::create([
      'name' => $this->randomString(),
      'id' => $this->generateRandomEntityId(),
      'type' => 'TEST_TYPE_ID',
      'type_string' => 'TEST_TYPE_STRING',
    ]);
    $channel->save();

    $errandService = ErrandService::create([
      'name' => $this->randomString(),
      'id' => $this->generateRandomEntityId(),
      'channels' => [
        ['entity' => $channel],
      ],
    ]);
    $errandService->save();

    $this->services[0] = Service::create([
      'name' => $this->randomString(),
      'id' => $this->generateRandomEntityId(),
      'errand_services' => [
        ['entity' => $errandService],
      ],
    ]);
    $this->services[0]->save();
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
          'id' => 'TEST_TYPE_ID',
          'label' => 'TEST_TYPE_STRING',
        ],
      ],
    ], $fields['channels']->getValues());
  }

}
