<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_recommendations\Kernel\EventSubscriber;

use Drupal\elasticsearch_connector\Event\BaseParamsEvent;
use Drupal\helfi_recommendations\Entity\SuggestedTopics;
use Drupal\helfi_recommendations\EventSubscriber\ElasticsearchParamsSubscriber;
use Drupal\helfi_recommendations\RecommendationManagerInterface;
use Drupal\node\Entity\Node;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\Tests\helfi_recommendations\Kernel\AnnifKernelTestBase;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that indexing and deletion reach the Elasticsearch params subscriber.
 */
#[Group('helfi_recommendations')]
#[RunTestsInSeparateProcesses]
class ElasticsearchParamsSubscriberTest extends AnnifKernelTestBase {

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
   * How many times the subscriber method was entered.
   */
  private \stdClass $calls;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('search_api', ['search_api_item']);
    $this->installConfig(['search_api']);

    // The connector reads http_client when the backend is first built. Keep
    // Elasticsearch off the network; the param builders still dispatch.
    $this->container->set('http_client', new Client([
      'handler' => static function () {
        return Create::promiseFor(new Response(200, [
          'X-Elastic-Product' => 'Elasticsearch',
          'Content-Type' => 'application/json',
        ], '{}'));
      },
    ]));

    $this->calls = new \stdClass();
    $this->calls->count = 0;
    $calls = $this->calls;
    $subscriber = new ElasticsearchParamsSubscriber($this->createMock(RecommendationManagerInterface::class));
    // The subscriber is final, so count calls from the service the dispatcher
    // resolves and forward them to the real method.
    $this->container->set(ElasticsearchParamsSubscriber::class, new class($subscriber, $calls) {

      public function __construct(
        private ElasticsearchParamsSubscriber $subscriber,
        private \stdClass $calls,
      ) {
      }

      /**
       * Counts a call, then runs the real subscriber.
       */
      public function invalidateCacheTags(BaseParamsEvent $event): void {
        $this->calls->count++;
        $this->subscriber->invalidateCacheTags($event);
      }

    });

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
    $calls = $this->calls->count;

    $node->setTitle('Updated title');
    $node->save();
    $this->index->indexItems();

    $this->assertGreaterThan($calls, $this->calls->count);
  }

  /**
   * Tests that deleting a referencing node reaches the subscriber.
   */
  public function testSubscriberIsReachedWhenReferencedNodeIsDeleted(): void {
    $node = $this->createNodeWithSuggestedTopics();
    $calls = $this->calls->count;

    $node->delete();

    $this->assertGreaterThan($calls, $this->calls->count);
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
