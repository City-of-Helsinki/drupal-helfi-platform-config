<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_paragraphs_news_list\Kernel;

use Drupal\KernelTests\KernelTestBase as CoreKernelTestBase;

/**
 * Kernel test base for news feed list tests.
 */
class KernelTestBase extends CoreKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'helfi_api_base',
    'field',
    'link',
    'image',
    'file',
    'user',
    'paragraphs',
    'external_entities',
    'responsive_image',
    'helfi_paragraphs_news_list',
    'text',
    'allowed_formats',
    'select2',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('file');
    $this->installEntitySchema('user');
    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('responsive_image_style');
    $this->installEntitySchema('paragraphs_type');
    $this->installConfig('image');
    $this->installConfig('paragraphs');
    $this->installConfig('helfi_paragraphs_news_list');
    $this->installEntitySchema('helfi_news');
    $this->installEntitySchema('helfi_news_groups');
    $this->installEntitySchema('helfi_news_neighbourhoods');
    $this->installEntitySchema('helfi_news_tags');
  }

}
