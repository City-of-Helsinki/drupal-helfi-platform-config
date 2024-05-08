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
    'file',
    'link',
    'user',
    'paragraphs',
    'external_entities',
    'text',
    'allowed_formats',
    'select2',
    'helfi_paragraphs_news_list',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'paragraphs', 'external_entities']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('paragraphs_type');
    $this->installConfig('helfi_paragraphs_news_list');
    $this->installEntitySchema('helfi_news');
    $this->installEntitySchema('helfi_news_tags');
    $this->installEntitySchema('helfi_news_groups');
    $this->installEntitySchema('helfi_news_neighbourhoods');
    $this->installConfig('paragraphs');
  }

}
