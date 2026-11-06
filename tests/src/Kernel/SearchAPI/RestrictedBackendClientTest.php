<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel\SearchAPI;

use Drupal\elasticsearch_connector\Plugin\search_api\backend\ElasticSearchBackend;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_platform_config\SearchAPI\RestrictedBackendClient;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\ServerInterface;
use Drupal\Tests\helfi_api_base\Traits\EnvironmentResolverTrait;
use Drupal\Tests\helfi_platform_config\Kernel\KernelTestBase;
use Drupal\Tests\helfi_platform_config\Traits\ElasticTrait;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the shared-index backend client against recorded HTTP requests.
 */
#[Group('helfi_platform_config')]
#[RunTestsInSeparateProcesses]
final class RestrictedBackendClientTest extends KernelTestBase {

  use ElasticTrait;
  use EnvironmentResolverTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'elasticsearch_connector',
    'search_api',
  ];

  /**
   * Recorded HTTP transactions.
   *
   * @var array<int, array<string, mixed>>
   */
  private array $history = [];

  /**
   * Responses returned for the next Elasticsearch calls.
   */
  private MockHandler $mockHandler;

  /**
   * The server whose backend client is under test.
   */
  private ServerInterface $server;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('search_api_task');

    $this->mockHandler = new MockHandler();
    $stack = HandlerStack::create($this->mockHandler);
    $history = &$this->history;
    $stack->push(Middleware::history($history));
    $this->container->set('http_client', new HttpClient(['handler' => $stack]));

    $this->server = Server::create([
      'id' => 'restricted_client',
      'name' => 'Restricted client',
      'status' => TRUE,
      'backend' => 'elasticsearch',
      'backend_config' => [
        'connector' => 'standard',
        'connector_config' => [
          'url' => 'http://elastic.test:9200',
          'enable_debug_logging' => FALSE,
        ],
        'advanced' => [
          'fuzziness' => 'auto',
          'prefix' => '',
          'suffix' => '',
          'synonyms' => ['city, town'],
        ],
      ],
    ]);
    $this->server->save();

    foreach (['embeddings', 'suggestions', 'news'] as $id) {
      Index::create([
        'id' => $id,
        'name' => $id,
        'status' => FALSE,
        'server' => $this->server->id(),
        'datasource_settings' => [],
        'tracker_settings' => [
          'default' => [],
        ],
      ])->save();
    }
  }

  /**
   * Operations that delete, create, or remap an Elasticsearch index.
   *
   * @return array<string, array{string}>
   *   The backend client method name.
   */
  public static function schemaOperations(): array {
    return [
      'remove' => ['removeIndex'],
      'add' => ['addIndex'],
      'clear' => ['clearIndex'],
      'settings' => ['updateSettings'],
      'mapping' => ['updateFieldMapping'],
    ];
  }

  /**
   * Shared indexes skip schema changes outside their main instance.
   */
  #[DataProvider('schemaOperations')]
  public function testSkipsSharedIndexOffMainInstance(string $method): void {
    $this->setActiveProject(Project::LIIKENNE, EnvironmentEnum::Local);

    $this->call($method, $this->index('embeddings'));

    $this->assertSame([], $this->history);
  }

  /**
   * The main instance still sends a request.
   */
  #[DataProvider('schemaOperations')]
  public function testRunsSharedIndexOnMainInstance(string $method): void {
    $this->setActiveProject(Project::ETUSIVU, EnvironmentEnum::Local);
    $this->queueResponses();

    $this->call($method, $this->index('suggestions'));

    $this->assertNotEmpty($this->history);
  }

  /**
   * Indexes that are not shared still send a request.
   */
  #[DataProvider('schemaOperations')]
  public function testRunsOtherIndexesOffMainInstance(string $method): void {
    $this->setActiveProject(Project::LIIKENNE, EnvironmentEnum::Local);
    $this->queueResponses();

    $this->call($method, $this->index('news'));

    $this->assertNotEmpty($this->history);
  }

  /**
   * A deleted index is passed as an id string to removeIndex().
   */
  public function testSkipsRemovedSharedIndexId(): void {
    $this->setActiveProject(Project::LIIKENNE, EnvironmentEnum::Local);

    $this->client()->removeIndex('embeddings');

    $this->assertSame([], $this->history);
  }

  /**
   * The backend client built from the server.
   */
  private function client(): RestrictedBackendClient {
    $backend = $this->server->getBackend();
    $this->assertInstanceOf(ElasticSearchBackend::class, $backend);
    $client = $backend->getBackendClient();
    $this->assertInstanceOf(RestrictedBackendClient::class, $client);
    return $client;
  }

  /**
   * Loads a saved index.
   */
  private function index(string $id): IndexInterface {
    $index = Index::load($id);
    $this->assertInstanceOf(IndexInterface::class, $index);
    return $index;
  }

  /**
   * Calls one schema operation.
   */
  private function call(string $method, IndexInterface $index): void {
    $client = $this->client();
    match ($method) {
      'removeIndex' => $client->removeIndex($index),
      'addIndex' => $client->addIndex($index),
      'clearIndex' => $client->clearIndex($index),
      'updateSettings' => $client->updateSettings($index),
      'updateFieldMapping' => $client->updateFieldMapping($index),
      default => throw new \InvalidArgumentException($method),
    };
  }

  /**
   * Queues enough successful responses for one connector call to finish.
   */
  private function queueResponses(): void {
    array_splice($this->history, 0);
    for ($i = 0; $i < 8; $i++) {
      $this->mockHandler->append($this->createElasticsearchResponse([
        'acknowledged' => TRUE,
      ]));
    }
  }

}
