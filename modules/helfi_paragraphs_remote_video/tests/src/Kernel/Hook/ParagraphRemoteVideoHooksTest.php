<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_paragraphs_remote_video\Kernel\Hook;

use Drupal\Core\Cache\Cache;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\helfi_paragraphs_remote_video\Entity\ParagraphRemoteVideo;
use Drupal\helfi_paragraphs_remote_video\Hook\ParagraphRemoteVideoHooks;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\Media;

/**
 * Tests paragraph remote video hooks.
 *
 * @group helfi_paragraphs_remote_video
 */
class ParagraphRemoteVideoHooksTest extends KernelTestBase {

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

    FieldStorageConfig::create([
      'field_name' => 'field_iframe_title',
      'entity_type' => 'paragraph',
      'type' => 'string',
    ])->save();

    $this->installConfig([
      'focal_point',
      'media_library',
      'helfi_media',
      'helfi_media_remote_video',
      'helfi_paragraphs_remote_video',
    ]);
  }

  /**
   * Tests preprocess hook for remote video paragraph.
   */
  public function testPreprocessParagraphRemoteVideo(): void {
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

    $variables = [
      'paragraph' => $paragraph,
      'content' => [
        'field_remote_video' => [
          0 => [
            '#cache' => [
              'tags' => [
                'media:' . $media->id(),
              ],
            ],
          ],
        ],
      ],
    ];

    ParagraphRemoteVideoHooks::preprocessParagraphRemoteVideo($variables);

    $this->assertArrayHasKey('is_hidden_video', $variables);
    $this->assertTrue($variables['is_hidden_video']);
    $media = $variables['paragraph']->get('field_remote_video')->first()->entity;
    $this->assertSame('Test iframe title', $media->iframeTitle ?? NULL);

    $expectedTags = Cache::mergeTags(
      ['media:' . $media->id()],
      $paragraph->getCacheTags(),
    );

    $this->assertSame(
      $expectedTags,
      $variables['content']['field_remote_video'][0]['#cache']['tags'],
    );
  }

  /**
   * Tests preprocess hook returns early for empty reference field.
   */
  public function testPreprocessParagraphRemoteVideoReturnsEarly(): void {
    /** @var \Drupal\helfi_paragraphs_remote_video\Entity\ParagraphRemoteVideo $paragraph */
    $paragraph = ParagraphRemoteVideo::create([
      'type' => 'remote_video',
      'field_iframe_title' => 'Test iframe title',
    ]);
    $paragraph->save();

    $variables = [
      'paragraph' => $paragraph,
      'content' => [],
    ];

    ParagraphRemoteVideoHooks::preprocessParagraphRemoteVideo($variables);

    $this->assertArrayNotHasKey('is_hidden_video', $variables);
  }

}
