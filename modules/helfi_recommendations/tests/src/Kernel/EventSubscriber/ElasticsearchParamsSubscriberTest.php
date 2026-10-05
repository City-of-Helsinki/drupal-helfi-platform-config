<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_recommendations\Kernel\EventSubscriber;

use Drupal\helfi_recommendations\Entity\SuggestedTopics;
use Drupal\helfi_recommendations\EventSubscriber\ElasticsearchParamsSubscriber;
use Drupal\node\Entity\Node;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\Tests\helfi_platform_config\Traits\ElasticKernelTrait;
use Drupal\Tests\helfi_recommendations\Kernel\AnnifKernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;

/**
 * Tests that indexing and deletion reach the Elasticsearch params subscriber.
 */
#[Group('helfi_recommendations')]
#[RunTestsInSeparateProcesses]
class ElasticsearchParamsSubscriberTest extends AnnifKernelTestBase {

  use ElasticKernelTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
  ];

  /**
   * The suggestions index.
   */
  private Index $index;

  /**
   * The subscriber prophecy.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy<\Drupal\helfi_recommendations\EventSubscriber\ElasticsearchParamsSubscriber>
   */
  private ObjectProphecy $subscriber;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('search_api', ['search_api_item']);
    $this->installConfig(['search_api']);

    $this->mockElasticsearchConnectorHttpClient();

    $this->subscriber = $this->prophesize(ElasticsearchParamsSubscriber::class);
    $this->container->set(ElasticsearchParamsSubscriber::class, $this->subscriber->reveal());

    $this->container->get('entity_type.manager')->getStorage('search_api_server')->resetCache();
    $this->container->get('entity_type.manager')->getStorage('search_api_index')->resetCache();

    $server = Server::create([
      'id' => 'recommendations_test',
      'name' => 'Recommendations test',
      'status' => TRUE,
      'backend' => 'elasticsearch',
      'backend_config' => [
        'connector' => 'standard',
        'connector_config' => [
          'url' => 'http://localhost:9200',
          'enable_debug_logging' => FALSE,
        ],
        'advanced' => [
          'fuzziness' => 'auto',
          'prefix' => '',
          'suffix' => '',
        ],
      ],
    ]);
    $server->save();

    $index = Index::load('suggestions') ?? Index::create([
      'id' => 'suggestions',
      'name' => 'Suggestions',
      'server' => 'recommendations_test',
      'datasource_settings' => [
        'entity:suggested_topics' => [],
      ],
      'tracker_settings' => [
        'default' => [],
      ],
    ]);
    $index->set('server', 'recommendations_test');
    $index->set('status', TRUE);
    // Match the installed index: updates are tracked now and indexed later.
    $index->setOption('index_directly', FALSE);
    $index->save();
    $this->index = $index;
  }

  /**
   * Tests that updating a referencing node reaches the subscriber.
   */
  public function testSubscriberIsReachedWhenReferencedNodeIsUpdated(): void {
    $node = $this->createNodeWithSuggestedTopics();
    // Index the insert so the next run only covers the update.
    $this->index->indexItems();
    $this->subscriber->invalidateCacheTags(Argument::cetera())
      ->shouldHaveBeenCalledOnce();

    $node->setTitle('Updated title');
    $node->save();
    $this->index->indexItems();

    $this->subscriber->invalidateCacheTags(Argument::cetera())
      ->shouldHaveBeenCalledTimes(2);
  }

  /**
   * Tests that deleting a referencing node reaches the subscriber.
   */
  public function testSubscriberIsReachedWhenReferencedNodeIsDeleted(): void {
    $node = $this->createNodeWithSuggestedTopics();
    $this->subscriber->invalidateCacheTags(Argument::cetera())
      ->shouldNotHaveBeenCalled();

    $node->delete();

    $this->subscriber->invalidateCacheTags(Argument::cetera())
      ->shouldHaveBeenCalledOnce();
  }

  /**
   * Creates a node that references a suggested topics entity.
   */
  private function createNodeWithSuggestedTopics(): Node {
    $node = Node::create([
      'type' => 'test_node_bundle',
      'title' => 'Suggested topics parent',
      'test_keywords' => SuggestedTopics::create(),
    ]);
    $node->save();
    return $node;
  }

}
