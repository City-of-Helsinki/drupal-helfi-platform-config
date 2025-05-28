<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_paragraphs_remote_video\Kernel\Entity;

use Drupal\helfi_paragraphs_remote_video\Entity\ParagraphRemoteVideo;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\Media;

/**
 * Tests the remote_video paragraph bundle class.
 *
 * @group helfi_paragraphs_remote_video
 */
class RemoteVideoParagraphTest extends KernelTestBase {

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
    'helfi_media_remote_video',
    'helfi_paragraphs_remote_video',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('media');
    $this->installConfig([
      'helfi_paragraphs_remote_video',
      'helfi_media_remote_video',
    ]);
  }

  /**
   * Tests the Remote video paragraph bundle behavior.
   */
  public function testRemoteVideoParagraphFieldsAndIframeTitle(): void {
    // Create a helfi_remote_video media entity.
    $media = Media::create([
      'bundle' => 'helfi_remote_video',
      'name' => 'Remote video media',
      'status' => 1,
    ]);
    $media->save();

    // Create Remote video paragraph with all relevant fields.
    $paragraph = ParagraphRemoteVideo::create([
      'type' => 'remote_video',
      'field_remote_video_title' => 'Test title',
      'field_remote_video_description' => 'Test description',
      'field_iframe_title' => 'Test iframe title',
      'field_remote_video' => [['target_id' => $media->id()]],
    ]);
    $paragraph->save();

    // Cast to custom Remote video class.
    $this->assertInstanceOf(ParagraphRemoteVideo::class, $paragraph);

    // Run the iframe title setter method.
    $paragraph->setMediaEntityIframeTitle();

    // Validate field_remote_video_title and
    // field_remote_video_description values.
    $this->assertEquals('Test title', $paragraph->get('field_remote_video_title')->value);
    $this->assertEquals('Test description', $paragraph->get('field_remote_video_description')->value);

    // Validate the iframe_title was set on the referenced media entity.
    $referenced = $paragraph->get('field_remote_video')->referencedEntities();
    $this->assertNotEmpty($referenced);
    $this->assertEquals('Test iframe title', $referenced[0]->iframeTitle ?? NULL);
  }

}
