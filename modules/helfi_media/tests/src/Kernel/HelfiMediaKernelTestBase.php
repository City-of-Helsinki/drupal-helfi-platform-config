<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_media\Kernel;

use Drupal\media\MediaInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\MediaStorage;

/**
 * Base class for helfi_media tests.
 *
 * @group helfi_media
 */
class HelfiMediaKernelTestBase extends KernelTestBase {

  /**
   * Media storage.
   *
   * @var \Drupal\media\MediaStorage
   */
  protected MediaStorage $mediaStorage;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_translation',
    'crop',
    'datetime',
    'field',
    'file',
    'focal_point',
    'helfi_media',
    'image',
    'language',
    'link',
    'media',
    'media_library',
    'path',
    'responsive_image',
    'system',
    'user',
    'views',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig([
      'system',
      'language',
    ]);
    $this->installEntitySchema('user');
    $this->installEntitySchema('media');
    $this->installEntitySchema('media_type');
    $this->installEntitySchema('crop');
    $this->installEntitySchema('crop_type');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
    $this->installConfig([
      'media',
      'media_library',
      'image',
      'crop',
      'focal_point',
      'helfi_media',
    ]);
    $this->mediaStorage = $this->container
      ->get('entity_type.manager')
      ->getStorage('media');
  }

  /**
   * Create a media entity.
   *
   * @param array $values
   *   The values for the media entity.
   *
   * @return \Drupal\media\MediaInterface|null
   *   Returns the media entity.
   */
  protected function createMediaEntity(array $values): ?MediaInterface {
    try {
      $media = $this->mediaStorage->create($values);
      $media->save();
      return $media;
    }
    catch (\Exception $e) {
      $this->fail($e->getMessage());
    }
  }

}
