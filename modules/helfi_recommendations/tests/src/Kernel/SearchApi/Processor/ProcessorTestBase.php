<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_recommendations\Kernel\SearchApi\Processor;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\ServerInterface;
use Drupal\search_api\Utility\Utility;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\search_api\Kernel\PostRequestIndexingTrait;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Base class for search api processor kernel tests.
 *
 * @group helfi_recommendations
 */
class ProcessorTestBase extends EntityKernelTestBase {

  use PostRequestIndexingTrait;
  use ProphecyTrait;

  /**
   * Test vocabulary.
   */
  protected Vocabulary $vocabulary;

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
    'taxonomy',
    'language',
    'helfi_recommendations',
    'search_api',
    'elasticsearch_connector',
    'helfi_api_base',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp($processor = NULL): void {
    parent::setUp();

    $this->installSchema('search_api', ['search_api_item']);
    $entities = [
      'search_api_task',
      'taxonomy_term',
      'suggested_topics',
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
        'entity:suggested_topics' => [],
      ],
      'server' => 'server',
      'tracker_settings' => [
        'default' => [],
      ],
    ]);
    $this->index->setServer($this->server);

    $this->vocabulary = Vocabulary::create([
      'vid' => 'tags',
    ]);
    $this->vocabulary->save();
  }

}
