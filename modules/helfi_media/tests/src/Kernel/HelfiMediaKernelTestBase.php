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
    'system',
    'link',
    'path',
    'field',
    'file',
    'image',
    'user',
    'views',
    'media',
    'datetime',
    'media_library',
    'helfi_media',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'media', 'media_library']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('media');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
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
