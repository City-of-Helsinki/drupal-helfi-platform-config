<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_recommendations\Kernel\Processor;

use Drupal\elasticsearch_connector\SearchAPI\BackendClientFactory;
use Drupal\elasticsearch_connector\SearchAPI\BackendClientInterface;
use Drupal\helfi_recommendations\Entity\SuggestedTopics;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Processor\ProcessorPropertyInterface;
use Drupal\search_api\ServerInterface;
use Drupal\search_api\Utility\Utility;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\search_api\Kernel\PostRequestIndexingTrait;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests the scored reference processor.
 *
 * @group helfi_recommendations
 */
class ScoredReferenceProcessorTest extends EntityKernelTestBase {

  use PostRequestIndexingTrait;
  use ProphecyTrait;

  /**
   * Test vocabulary.
   */
  private Vocabulary $vocabulary;

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

    $searchApiField = new Field($this->index, 'keywords');
    $searchApiField->setType('scored_item');
    $searchApiField->setPropertyPath('keywords_scored');
    $searchApiField->setLabel('Test field');
    $searchApiField->setDatasourceId('entity:suggested_topics');

    $this->index->addField($searchApiField);
    $this->index->setOption('index_directly', TRUE);
    $this->index->save();

    $this->vocabulary = Vocabulary::create([
      'vid' => 'tags',
    ]);
    $this->vocabulary->save();
  }

  /**
   * Tests that field values are added correctly.
   */
  public function testDatasource() : void {
    /** @var \Drupal\search_api\Utility\PluginHelperInterface $pluginHelper */
    $pluginHelper = $this->container->get('search_api.plugin_helper');

    $term = Term::create([
      'name' => $this->randomMachineName(),
      'vid' => $this->vocabulary->id(),
    ]);
    $term->save();

    SuggestedTopics::create([
      'keywords' => [
        [
          'entity' => $term,
          'score' => 0.5,
        ],
      ],
    ])->save();

    $datasource = $pluginHelper->createDatasourcePlugin($this->index, 'entity:suggested_topics');
    $sut = $pluginHelper->createProcessorPlugin($this->index, 'scored_reference');

    $properties = $sut->getPropertyDefinitions(NULL);
    $this->assertEmpty($properties);

    $properties = $sut->getPropertyDefinitions($datasource);
    $this->assertNotEmpty($properties);
    $this->assertArrayHasKey('keywords_scored', $properties);
    $this->assertInstanceOf(ProcessorPropertyInterface::class, $properties['keywords_scored']);
  }

  /**
   * Tests that field values are added correctly.
   */
  public function testAddFieldValues() : void {
    $backend = $this->prophesize(BackendClientInterface::class);
    $backend
      ->indexItems(Argument::any(), Argument::any())
      ->shouldBeCalled()
      ->willReturn([]);

    $backend
      ->addIndex(Argument::any());

    $backendFactory = $this->prophesize(BackendClientFactory::class);
    $backendFactory->create(Argument::any(), Argument::any())
      ->willReturn($backend->reveal());

    $this->container->set('elasticsearch_connector.backend_client_factory', $backendFactory->reveal());

    $term = Term::create([
      'name' => $this->randomMachineName(),
      'vid' => $this->vocabulary->id(),
    ]);
    $term->save();

    SuggestedTopics::create([
      'keywords' => [
        [
          'entity' => $term,
          'score' => 0.5,
        ],
      ],
    ])->save();

    $this->triggerPostRequestIndexing();
  }

}
