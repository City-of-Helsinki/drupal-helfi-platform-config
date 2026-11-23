<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel\SearchAPI;

use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_platform_config\SearchAPI\RestrictedElasticSearchBackend;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\ServerInterface;
use Drupal\Tests\helfi_api_base\Traits\EnvironmentResolverTrait;
use Drupal\Tests\helfi_platform_config\Kernel\KernelTestBase;
use Drupal\Tests\helfi_platform_config\Traits\ElasticKernelTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the shared-index backend against recorded HTTP requests.
 */
#[Group('helfi_platform_config')]
#[RunTestsInSeparateProcesses]
final class RestrictedElasticSearchBackendTest extends KernelTestBase {

  use ElasticKernelTrait;
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
   * The server whose backend is under test.
   */
  private ServerInterface $server;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('search_api_task');

    // Every Elasticsearch request succeeds and is recorded.
    $this->mockElasticsearchConnectorHttpClient(['acknowledged' => TRUE], $this->history);

    $this->server = Server::create([
      'id' => 'restricted_backend',
      'name' => 'Restricted backend',
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
   *   The backend method name.
   */
  public static function schemaOperations(): array {
    return [
      'remove' => ['removeIndex'],
      'add' => ['addIndex'],
      'update' => ['updateIndex'],
      'clear' => ['deleteAllIndexItems'],
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

    $this->call($method, $this->index('suggestions'));

    $this->assertNotEmpty($this->history);
  }

  /**
   * Indexes that are not shared still send a request.
   */
  #[DataProvider('schemaOperations')]
  public function testRunsOtherIndexesOffMainInstance(string $method): void {
    $this->setActiveProject(Project::LIIKENNE, EnvironmentEnum::Local);

    $this->call($method, $this->index('news'));

    $this->assertNotEmpty($this->history);
  }

  /**
   * A deleted index is passed as an id string to removeIndex().
   */
  public function testSkipsRemovedSharedIndexId(): void {
    $this->setActiveProject(Project::LIIKENNE, EnvironmentEnum::Local);

    $this->backend()->removeIndex('embeddings');

    $this->assertSame([], $this->history);
  }

  /**
   * Document deletion and reads still reach a shared index off its main.
   */
  public function testPassesOtherOperationsThroughOffMainInstance(): void {
    $this->setActiveProject(Project::LIIKENNE, EnvironmentEnum::Local);
    $backend = $this->backend();
    $index = $this->index('embeddings');

    $operations = [
      'deleteItems' => static fn () => $backend->deleteItems($index, ['entity:node/1:fi']),
      'getRawIndexMappings' => static fn () => $backend->getRawIndexMappings($index),
      'getRawIndexSettings' => static fn () => $backend->getRawIndexSettings($index),
    ];
    foreach ($operations as $operation => $call) {
      $this->history = [];
      $call();
      $this->assertCount(1, $this->history, $operation);
    }
  }

  /**
   * A breaking field change off the main instance only marks items.
   *
   * The main instance rebuilds the shared Elasticsearch index, which drops
   * this instance's documents, so they must be indexed again.
   */
  public function testBreakingUpdateOffMainInstanceMarksItemsForReindexing(): void {
    $this->setActiveProject(Project::LIIKENNE, EnvironmentEnum::Local);
    $index = $this->index('embeddings');
    $index->setStatus(TRUE);
    // New indexes count as reindexing until items get indexed.
    $this->container->get('state')->set('search_api.index.embeddings.has_reindexed', FALSE);

    // A change Elasticsearch can apply in place leaves the items as they are.
    $index->setOriginal(clone $index);
    $this->backend()->updateIndex($index);
    $this->assertFalse($index->isReindexing());

    // The previous version had a field that the new one drops.
    $original = clone $index;
    $original->addField($this->container->get('search_api.fields_helper')->createField($original, 'title', [
      'type' => 'string',
    ]));
    $index->setOriginal($original);
    $this->backend()->updateIndex($index);

    $this->assertTrue($index->isReindexing());
    $this->assertSame([], $this->history);
  }

  /**
   * The backend plugin built for the server.
   */
  private function backend(): RestrictedElasticSearchBackend {
    $backend = $this->server->getBackend();
    $this->assertInstanceOf(RestrictedElasticSearchBackend::class, $backend);
    return $backend;
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
    $backend = $this->backend();
    match ($method) {
      'removeIndex' => $backend->removeIndex($index),
      'addIndex' => $backend->addIndex($index),
      'updateIndex' => $backend->updateIndex($index),
      'deleteAllIndexItems' => $backend->deleteAllIndexItems($index),
      default => throw new \InvalidArgumentException($method),
    };
  }

}
