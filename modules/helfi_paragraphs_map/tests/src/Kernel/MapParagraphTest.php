<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_paragraphs_map\Kernel\Entity;

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
    // Core modules.
    'content_translation',
    'entity',
    'field',
    'file',
    'filter',
    'language',
    'link',
    'media',
    'media_library',
    'options',
    'system',
    'taxonomy',
    'text',
    'user',
    'views',

    // Contrib modules.
    'allowed_formats',
    'crop',
    'linkit',
    'paragraphs',
    'readonly_field_widget',

    // Custom / HELFI modules.
    'hdbt_admin_tools',
    'helfi_api_base',
    'helfi_media',
    'helfi_media_map',
    'helfi_paragraphs_map',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('media');
    $this->installConfig([
      'helfi_paragraphs_map',
      'helfi_media_map',
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
