<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_paragraphs_map\Kernel\Entity;

use Drupal\field\Entity\FieldStorageConfig;
use Drupal\helfi_paragraphs_map\Entity\Map;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\Media;

/**
 * Tests the map paragraph bundle class.
 *
 * @group helfi_paragraphs_map
 */
class MapParagraphTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'allowed_formats',
    'content_translation',
    'crop',
    'entity_reference_revisions',
    'field',
    'file',
    'focal_point',
    'helfi_media',
    'helfi_media_map',
    'helfi_paragraphs_map',
    'image',
    'language',
    'link',
    'linkit',
    'media',
    'media_library',
    'paragraphs',
    'paragraphs_library',
    'responsive_image',
    'system',
    'text',
    'user',
    'views',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['system', 'paragraphs']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('media');
    $this->installEntitySchema('crop');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);

    // Field storage config lives in helfi_base_content module,
    // which would pull a lot of dependencies to the test.
    FieldStorageConfig::create([
      'field_name' => 'field_iframe_title',
      'entity_type' => 'paragraph',
      'type' => 'string',
    ])->save();

    // Then install the rest of your module configs.
    $this->installConfig([
      'focal_point',
      'media_library',
      'helfi_media',
      'helfi_media_map',
      'helfi_paragraphs_map',
    ]);
  }

  /**
   * Tests the Map paragraph bundle behavior.
   */
  public function testMapParagraphFieldsAndIframeTitle(): void {
    // Create a hel_map media entity.
    $media = Media::create([
      'bundle' => 'hel_map',
      'name' => 'Map media',
      'status' => 1,
    ]);
    $media->save();

    // Create Map paragraph with all relevant fields.
    $paragraph = Map::create([
      'type' => 'map',
      'field_map_title' => 'Test title',
      'field_map_description' => 'Test description',
      'field_iframe_title' => 'Test iframe title',
      'field_map_map' => [['target_id' => $media->id()]],
    ]);
    $paragraph->save();

    // Cast to custom Map class.
    $this->assertInstanceOf(Map::class, $paragraph);

    // Run the iframe title setter method.
    $paragraph->setMediaEntityIframeTitle();

    // Validate field_map_title and field_map_description values.
    $this->assertEquals('Test title', $paragraph->get('field_map_title')->value);
    $this->assertEquals('Test description', $paragraph->get('field_map_description')->value);

    // Validate the iframe_title was set on the referenced media entity.
    $referenced = $paragraph->get('field_map_map')->referencedEntities();
    $this->assertNotEmpty($referenced);
    $this->assertEquals('Test iframe title', $referenced[0]->iframeTitle ?? NULL);
  }

}
