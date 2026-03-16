<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_paragraphs_remote_video\Kernel\Entity;

use Drupal\field\Entity\FieldStorageConfig;
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
    'allowed_formats',
    'breakpoint',
    'content_translation',
    'crop',
    'entity_reference_revisions',
    'field',
    'file',
    'focal_point',
    'helfi_api_base',
    'helfi_media',
    'helfi_media_remote_video',
    'helfi_paragraphs_remote_video',
    'image',
    'language',
    'link',
    'linkit',
    'media',
    'media_library',
    'oembed_providers',
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

    // Install the rest of your module configs.
    $this->installConfig([
      'focal_point',
      'media_library',
      'helfi_media',
      'helfi_media_remote_video',
      'helfi_paragraphs_remote_video',
    ]);
  }

  /**
   * Tests setting iframe title to referenced media.
   */
  public function testSetMediaEntityIframeTitle(): void {
    $media = Media::create([
      'bundle' => 'remote_video',
      'name' => 'Remote video media',
      'status' => 1,
    ]);
    $media->save();

    /** @var \Drupal\helfi_paragraphs_remote_video\Entity\ParagraphRemoteVideo $paragraph */
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
    $this->assertSame($media->id(), $paragraph->getReferencedMediaEntity()?->id());

    // Run the iframe title setter method.
    $paragraph->setMediaEntityIframeTitle();

    // Validate remote video title and description values.
    $this->assertSame('Test title', $paragraph->get('field_remote_video_title')->value);
    $this->assertSame('Test description', $paragraph->get('field_remote_video_description')->value);
    $this->assertSame('Test iframe title', $paragraph->getReferencedMediaEntity()?->iframeTitle ?? NULL);
  }

  /**
   * Tests hidden video detection when referenced media has no video URL.
   */
  public function testIsHiddenVideo(): void {
    $media = Media::create([
      'bundle' => 'remote_video',
      'name' => 'Remote video media',
      'status' => 1,
    ]);
    $media->save();

    /** @var \Drupal\helfi_paragraphs_remote_video\Entity\ParagraphRemoteVideo $paragraph */
    $paragraph = ParagraphRemoteVideo::create([
      'type' => 'remote_video',
      'field_iframe_title' => 'Test iframe title',
      'field_remote_video' => [
        ['target_id' => $media->id()],
      ],
    ]);
    $paragraph->save();
    $this->assertTrue($paragraph->isHiddenVideo());
  }

  /**
   * Tests hidden video detection without referenced media.
   */
  public function testIsHiddenVideoWithoutReferencedMedia(): void {
    /** @var \Drupal\helfi_paragraphs_remote_video\Entity\ParagraphRemoteVideo $paragraph */
    $paragraph = ParagraphRemoteVideo::create([
      'type' => 'remote_video',
      'field_iframe_title' => 'Test iframe title',
    ]);
    $paragraph->save();

    $this->assertFalse($paragraph->isHiddenVideo());
  }

}
