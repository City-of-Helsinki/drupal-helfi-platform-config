<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_react_search\Kernel\Plugin\SearchApi\Processor;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\ServerInterface;
use Drupal\search_api\Utility\Utility;
use Drupal\Tests\search_api\Kernel\PostRequestIndexingTrait;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Base class for search api service processor kernel tests.
 *
 * @group helfi_react_search
 */
class ServiceProcessorTestBase extends EntityKernelTestBase {

  use PostRequestIndexingTrait;
  use ProphecyTrait;

  /**
   * The processor used for this test.
   *
   * @var \Drupal\search_api\Processor\ProcessorInterface
   */
  protected $processor;

  /**
   * The search index used for this test.
   */
  protected IndexInterface $index;

  /**
   * The search server used for this test.
   */
  protected ServerInterface $server;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'helfi_react_search',
    'helfi_platform_config',
    'config_rewrite',
    'search_api',
    'elasticsearch_connector',
    'helfi_api_base',
    'helfi_tpr',
    'menu_link_content',
    'link',
    'media',
    'address',
    'telephone',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp($processor = NULL): void {
    parent::setUp();

    $this->installSchema('search_api', ['search_api_item']);
    $entities = [
      'menu_link_content',
      'search_api_task',
      'tpr_service',
    ];
    foreach ($entities as $entity) {
      $this->installEntitySchema($entity);
    }

    $this->installConfig(['system']);
    $this->installConfig('search_api');
    $this->installConfig(['elasticsearch_connector']);

    // Do not use a batch for tracking the initial items after creating an
    // index when running the tests via the GUI. Otherwise, it seems Drupal's
    // Batch API gets confused and the test fails.
    if (!Utility::isRunningInCli()) {
      \Drupal::state()->set('search_api_use_tracking_batch', FALSE);
    }

    $this->server = Server::create([
      'id' => 'server',
      'name' => 'Server & Name',
      'status' => TRUE,
      'backend' => 'elasticsearch',
      'backend_config' => [
        'connector' => 'standard',
        'connector_config' => [
          'url' => 'http://elastic:9200',
          'enable_debug_logging' => TRUE,
        ],
      ],
    ]);
    $this->server->save();

    $this->index = Index::create([
      'id' => 'index',
      'name' => 'Index name',
      'status' => TRUE,
      'datasource_settings' => [
        'entity:tpr_service' => [],
      ],
      'server' => 'server',
      'tracker_settings' => [
        'default' => [],
      ],
    ]);
    $this->index->setServer($this->server);

    if ($processor) {
      $this->processor = \Drupal::getContainer()
        ->get('search_api.plugin_helper')
        ->createProcessorPlugin($this->index, $processor);
      $this->index->addProcessor($this->processor);
    }
    $this->index->save();
  }

}
