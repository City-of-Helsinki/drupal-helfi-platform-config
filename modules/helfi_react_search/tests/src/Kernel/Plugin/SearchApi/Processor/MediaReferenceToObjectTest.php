<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_react_search\Kernel\Plugin\SearchApi\Processor;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\helfi_tpr\Entity\Service;
use Drupal\image\Entity\ImageStyle;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Utility\Utility;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests the media reference to object processor.
 */
#[Group('helfi_react_search')]
class MediaReferenceToObjectTest extends ServiceProcessorTestBase {

  /**
   * Image style machine names used by the processor.
   */
  private const IMAGE_STYLES = [
    '1_5_304w_203h',
    '1_5_294w_196h',
    '1_5_220w_147h',
    '1_5_176w_118h',
    '1_5_511w_341h',
    '1_5_608w_406w_lq',
    '1_5_588w_392h_lq',
    '1_5_440w_294h_lq',
    '1_5_352w_236h_lq',
    '1_5_1022w_682h_lq',
  ];

  /**
   * The media reference field machine name.
   */
  private const MEDIA_FIELD_NAME = 'field_service_media';

  /**
   * Test service entities.
   *
   * @var \Drupal\helfi_tpr\Entity\Service[]
   */
  protected $services;

  /**
   * {@inheritdoc}
   *
   * @phpstan-param string|null $processor
   */
  public function setUp($processor = NULL): void {
    parent::setUp('media_reference_to_object');

    $configuration = $this->processor->getConfiguration();
    $configuration['fields'] = [
      self::MEDIA_FIELD_NAME => TRUE,
    ];
    $this->processor->setConfiguration($configuration);

    $this->installEntitySchema('image_style');
    $this->installEntitySchema('media');
    $this->installEntitySchema('media_type');
    $this->installEntitySchema('file');
    $this->installSchema('file', 'file_usage');

    $searchApiField = new Field($this->index, 'media_as_objects');
    $searchApiField->setType('object');
    $searchApiField->setPropertyPath('media_as_objects');
    $searchApiField->setLabel('Media as objects');

    $this->index->addField($searchApiField);
    $this->index->setOption('index_directly', TRUE);
    $this->index->save();

    foreach (self::IMAGE_STYLES as $styleName) {
      ImageStyle::create([
        'label' => $styleName,
        'name' => $styleName,
      ])->save();
    }

    $image = File::create([
      'filename' => 'test.jpg',
      'uri' => 'public://test.jpg',
      'filemime' => 'image/jpeg',
      'filesize' => 4,
      'status' => TRUE,
      'uid' => 1,
    ]);
    $image->save();

    MediaType::create([
      'name' => 'Image',
      'id' => 'image',
      'source' => 'image',
      'source_configuration' => [
        'source_field' => 'field_media_image',
      ],
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_media_image',
      'entity_type' => 'media',
      'type' => 'image',
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_media_image',
      'entity_type' => 'media',
      'bundle' => 'image',
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_photographer',
      'entity_type' => 'media',
      'type' => 'string',
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_photographer',
      'entity_type' => 'media',
      'bundle' => 'image',
    ])->save();

    FieldStorageConfig::create([
      'field_name' => self::MEDIA_FIELD_NAME,
      'entity_type' => 'tpr_service',
      'type' => 'entity_reference',
      'settings' => [
        'target_type' => 'media',
      ],
    ])->save();

    FieldConfig::create([
      'field_name' => self::MEDIA_FIELD_NAME,
      'entity_type' => 'tpr_service',
      'bundle' => 'tpr_service',
    ])->save();

    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();

    $media = Media::create([
      'name' => 'Test media',
      'bundle' => 'image',
      'field_media_image' => [
        [
          'target_id' => $image->id(),
          'alt' => 'Test alt',
          'title' => 'Test title',
        ],
      ],
      'field_photographer' => [
        ['value' => 'Test photographer'],
      ],
    ]);
    $media->save();

    $this->services[0] = Service::create([
      'name' => $this->randomString(),
      'id' => $this->generateRandomEntityId(),
      self::MEDIA_FIELD_NAME => [
        ['target_id' => $media->id()],
      ],
    ]);
    $this->services[0]->save();
  }

  /**
   * Tests that field values are added correctly.
   */
  #[Test]
  public function testAddFieldValues(): void {
    $id = Utility::createCombinedId('entity:tpr_service', $this->services[0]->id() . ':en');
    $item = \Drupal::getContainer()
      ->get('search_api.fields_helper')
      ->createItemFromObject($this->index, $this->services[0]->getTypedData(), $id);

    $fields = $item->getFields();
    $values = $fields['media_as_objects']->getValues();
    $mediaValues = $values[0][self::MEDIA_FIELD_NAME];

    $this->assertEquals('Test alt', $mediaValues['alt']);
    $this->assertEquals('Test photographer', $mediaValues['photographer']);
    $this->assertEquals('Test title', $mediaValues['title']);
    $this->assertArrayHasKey('variants', $mediaValues);
    $this->assertArrayHasKey('1248', $mediaValues['variants']);
    $this->assertEquals($mediaValues['variants']['1248'], $mediaValues['url']);
    $this->assertIsArray($mediaValues['variants']);
    $this->assertCount(10, $mediaValues['variants']);
  }

}
