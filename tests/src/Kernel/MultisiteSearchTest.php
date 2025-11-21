<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Entity\Index;
use Drupal\helfi_platform_config\MultisiteSearch;
use Drupal\Tests\helfi_api_base\Traits\EnvironmentResolverTrait;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;

/**
 * Tests MultisiteSearch service.
 */
class MultisiteSearchTest extends EntityKernelTestBase {

  use EnvironmentResolverTrait;

  /**
   * The multisitesearch index used for this test.
   */
  protected IndexInterface $multisiteSearchIndex;

  /**
   * The single site search index used for this test.
   */
  protected IndexInterface $singleSiteSearchIndex;

  /**
   * The service to test.
   */
  protected MultisiteSearch $multisiteSearch;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'config_rewrite',
    'elasticsearch_connector',
    'helfi_api_base',
    'helfi_platform_config',
    'search_api',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('search_api_task');

    $this->multisiteSearchIndex = Index::create([
      'id' => 'multisite_search_index',
      'name' => 'Multisite search index',
      'status' => TRUE,
      'datasource_settings' => [],
      // 'server' => 'server',
      'tracker_settings' => [
        'default' => [],
      ],
      'options' => [
        'helfi_platform_config_multisite' => TRUE,
      ],
    ]);
    $this->multisiteSearchIndex->save();

    $this->singleSiteSearchIndex = Index::create([
      'id' => 'single_site_search_index',
      'name' => 'Single site search index',
      'status' => TRUE,
      'datasource_settings' => [],
      // 'server' => 'server',
      'tracker_settings' => [
        'default' => [],
      ],
      'options' => [
        'helfi_platform_config_multisite' => FALSE,
      ],
    ]);
    $this->singleSiteSearchIndex->save();

    $this->multisiteSearch = new MultisiteSearch($this->getEnvironmentResolver('test_project', EnvironmentEnum::Local), $this->entityTypeManager);
  }

  /**
   * Tests the isMultisiteIndex method.
   */
  public function testIsMultisiteIndex(): void {
    $this->assertTrue($this->multisiteSearch->isMultisiteIndex('multisite_search_index'));
    $this->assertFalse($this->multisiteSearch->isMultisiteIndex('single_site_search_index'));
    $this->assertFalse($this->multisiteSearch->isMultisiteIndex('non_existent_index'));
  }

}
