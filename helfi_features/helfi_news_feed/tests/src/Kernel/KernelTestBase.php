<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_news_feed\Kernel;

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
    'helfi_news_feed',
    'helfi_tpr',
    'text',
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
    $this->installConfig('helfi_news_feed');
    $this->installEntitySchema('helfi_news');
  }

}
