<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_paragraphs_news_list\Kernel;

use DG\BypassFinals;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\KernelTests\KernelTestBase as CoreKernelTestBase;
use Elastic\Elasticsearch\Response\Elasticsearch;

/**
 * Kernel test base for news feed list tests.
 */
abstract class KernelTestBase extends CoreKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'helfi_api_base',
    'config_rewrite',
    'helfi_platform_config',
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
    // https://github.com/elastic/elasticsearch-php/issues/1227.
    BypassFinals::enable();

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

  /**
   * Mocks elasticsearch response.
   *
   * @param array $response
   *   Response as an array.
   */
  protected function createElasticsearchResponse(array $response): Elasticsearch {
    $mock = $this->prophesize(Elasticsearch::class);
    $mock->asArray()->willReturn($response);
    return $mock->reveal();
  }

}
