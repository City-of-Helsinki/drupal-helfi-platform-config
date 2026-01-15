<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_paragraphs_news_list\Kernel;

use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\KernelTests\KernelTestBase as CoreKernelTestBase;
use Drupal\Tests\helfi_platform_config\Traits\ElasticTrait;

/**
 * Kernel test base for news feed list tests.
 */
abstract class KernelTestBase extends CoreKernelTestBase {

  use ElasticTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'helfi_api_base',
    'config_rewrite',
    'helfi_platform_config',
    'entity_reference_revisions',
    'field',
    'file',
    'link',
    'user',
    'options',
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

    // Triggers rebuilding routes.
    // https://www.drupal.org/project/external_entities/issues/3549828.
    $this->container
      ->get(RouteProviderInterface::class)
      ->getAllRoutes();

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
