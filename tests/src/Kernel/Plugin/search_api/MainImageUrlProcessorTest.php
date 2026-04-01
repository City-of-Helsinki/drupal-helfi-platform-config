<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel\Plugin\search_api;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\helfi_platform_config\Plugin\search_api\processor\Property\MainImageProperty;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Utility\Utility;
use Drupal\Tests\search_api\Kernel\Processor\ProcessorTestBase;
use Drupal\Tests\TestFileCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests for the MainImageUrl processor.
 */
#[Group('helfi_platform_config')]
#[RunTestsInSeparateProcesses]
class MainImageUrlProcessorTest extends ProcessorTestBase {

  use TestFileCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_platform_config',
    'media',
    'image',
    'file',
    'config_rewrite',
    'helfi_api_base',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp($processor = NULL): void {
    parent::setUp('main_image_url');

    $this->installEntitySchema('file');
    $this->installSchema('file', 'file_usage');
    $this->installEntitySchema('media');

    $this->installConfig(['field', 'system', 'image', 'media', 'user']);

    MediaType::create([
      'id' => 'image',
      'label' => 'Image',
      'source' => 'image',
      'source_configuration' => [
        'source_field' => 'field_media_image',
      ],
    ])->save();

    // Create the source field storage for media image.
    FieldStorageConfig::create([
      'field_name' => 'field_media_image',
      'entity_type' => 'media',
      'type' => 'image',
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_media_image',
      'entity_type' => 'media',
      'bundle' => 'image',
      'label' => 'Image',
    ])->save();

    NodeType::create([
      'label' => 'Article',
      'type' => 'article',
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_main_image',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => [
        'target_type' => 'media',
      ],
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_main_image',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Media',
      'settings' => [
        'handler' => 'default:media',
        'handler_settings' => [
          'target_bundles' => ['image' => 'image'],
        ],
      ],
    ])->save();

    $field = new Field($this->index, 'main_image_url');
    $field->setType('string');
    $field->setConfiguration([
      'field_name' => 'field_main_image',
      'image_styles' => [
        ['id' => 'medium', 'breakpoint' => '1024'],
        ['id' => 'large', 'breakpoint' => '2048'],
      ],
    ]);
    $field->setDatasourceId('entity:node');
    $field->setPropertyPath('main_image_url');
    $field->setLabel('Main image URL');
    $this->index->addField($field);
    $this->index->save();
  }

  /**
   * Tests MainImageProperty default configuration.
   */
  #[Test]
  public function testMainImageProperty(): void {
    $property = new MainImageProperty([]);
    $defaultConfiguration = $property->defaultConfiguration();
    $this->assertNull($defaultConfiguration['field_name']);
    $this->assertNotEmpty($defaultConfiguration['image_styles']);

    foreach ($defaultConfiguration['image_styles'] as $style) {
      $this->assertArrayHasKey('breakpoint', $style);
      $this->assertArrayHasKey('id', $style);
    }
  }

  /**
   * Tests that the image style data is extracted into the field.
   */
  #[Test]
  public function testAddFieldValues(): void {
    $file = File::create([
      'uri' => $this->getTestFiles('image')[0]->uri,
    ]);
    $file->setPermanent();
    $file->save();

    $media = Media::create([
      'bundle' => 'image',
      'name' => 'Test',
      'field_media_image' => ['target_id' => $file->id()],
    ]);
    $media->save();

    $node = Node::create([
      'title' => 'My Test Node',
      'type' => 'article',
      'field_main_image' => ['target_id' => $media->id()],
    ]);
    $node->save();

    $id = Utility::createCombinedId('entity:node', $node->id() . ':en');
    $item = \Drupal::getContainer()
      ->get('search_api.fields_helper')
      ->createItemFromObject($this->index, $node->getTypedData(), $id);

    $values = $item->getField('main_image_url')->getValues();
    $this->assertArrayHasKey(0, $values);

    $json = json_decode($values[0]);

    $this->assertStringEndsWith('/files/image-test.png', $json->original->url);
    $this->assertEquals('image/png', $json->original->mime);
    $this->assertTrue($json->original->size > 0);

    $this->assertStringContainsString('/files/styles/medium/public/image-test.png.avif', $json->styles[0]->url);
    $this->assertEquals('1024', $json->styles[0]->breakpoint);

    $this->assertStringContainsString('/files/styles/large/public/image-test.png.avif', $json->styles[1]->url);
    $this->assertEquals('2048', $json->styles[1]->breakpoint);
  }

}
