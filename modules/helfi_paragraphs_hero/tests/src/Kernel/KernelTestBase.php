<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_paragraphs_hero\Kernel;

use Drupal\KernelTests\KernelTestBase as CoreKernelTestBase;

/**
 * Kernel test base for news feed list tests.
 */
class KernelTestBase extends CoreKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'allowed_formats',
    'content_translation',
    'crop',
    'entity',
    'field',
    'file',
    'filter',
    'focal_point',
    'hdbt_admin_tools',
    'helfi_media',
    'helfi_paragraphs_hero',
    'image',
    'language',
    'link',
    'linkit',
    'media',
    'media_library',
    'options',
    'paragraphs',
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
    $this->installEntitySchema('paragraphs_type');
    $this->installEntitySchema('media');
    $this->installEntitySchema('media_type');
    $this->installEntitySchema('file');
    $this->installEntitySchema('crop');
    $this->installEntitySchema('crop_type');
    $this->installSchema('file', ['file_usage']);

    $this->installConfig([
      'media',
      'image',
      'media_library',
      'crop',
      'focal_point',
      'helfi_media',
      'helfi_paragraphs_hero',
      'hdbt_admin_tools',
      'filter',
    ]);
  }

}
