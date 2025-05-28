<?php

declare(strict_types=1);

use Drupal\helfi_paragraphs_chart\Entity\Chart;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\Media;

/**
 * Tests the chart paragraph bundle class.
 * 
 * @group helfi_paragraphs_chart
 */
class ChartParagraphTest extends KernelTestBase {

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
    'responsive_image',
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
    'helfi_media_chart',
    'helfi_paragraphs_chart',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('media');
    $this->installConfig([
      'helfi_paragraphs_chart',
      'helfi_media_chart',
    ]);
  }

  /**
   * Tests the Chart paragraph bundle behavior.
   */
  public function testChartParagraphFieldsAndIframeTitle(): void {
    // Create a helfi_chart media entity.
    $media = Media::create([
      'bundle' => 'helfi_chart',
      'name' => 'Chart media',
      'status' => 1,
    ]);
    $media->save();

    // Create Chart paragraph with all relevant fields.
    $paragraph = Chart::create([
      'type' => 'chart',
      'field_chart_title' => 'Test title',
      'field_chart_description' => 'Test description',
      'field_iframe_title' => 'Test iframe title',
      'field_chart_chart' => [['target_id' => $media->id()]],
    ]);
    $paragraph->save();

    // Cast to custom Chart class.
    $this->assertInstanceOf(Chart::class, $paragraph);

    // Run the iframe title setter method.
    $paragraph->setMediaEntityIframeTitle();

    // Validate field_chart_title and field_chart_description values.
    $this->assertEquals('Test title', $paragraph->get('field_chart_title')->value);
    $this->assertEquals('Test description', $paragraph->get('field_chart_description')->value);

    // Validate the iframe_title was set on the referenced media entity.
    $referenced = $paragraph->get('field_chart_chart')->referencedEntities();
    $this->assertNotEmpty($referenced);
    $this->assertEquals('Test iframe title', $referenced[0]->iframe_title ?? NULL);
  }

}
