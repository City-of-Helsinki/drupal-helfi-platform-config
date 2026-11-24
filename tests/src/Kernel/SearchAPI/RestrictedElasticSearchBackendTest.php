<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel\SearchAPI;

use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_platform_config\SearchAPI\RestrictedElasticSearchBackend;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\SearchApiException;
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
   * Other instances do not create or delete a shared index.
   */
  public function testSkipsAddAndRemoveOffMainInstance(): void {
    $this->setActiveProject(Project::LIIKENNE, EnvironmentEnum::Local);

    $this->backend()->addIndex($this->index('embeddings'));
    $this->backend()->removeIndex($this->index('embeddings'));

    $this->assertSame([], $this->history);
  }

  /**
   * Other instances only add field mappings to a shared index.
   */
  public function testUpdateOffMainInstanceOnlyPutsMappings(): void {
    $this->setActiveProject(Project::LIIKENNE, EnvironmentEnum::Local);

    $this->backend()->updateIndex($this->index('embeddings'));

    $this->assertSame([
      'HEAD /embeddings',
      'PUT /embeddings/_mapping',
    ], $this->requests());
  }

  /**
   * Clearing a shared index deletes only this instance's documents.
   */
  public function testClearDeletesOnlyOwnDocuments(): void {
    $this->setActiveProject(Project::LIIKENNE, EnvironmentEnum::Local);

    $this->backend()->deleteAllIndexItems($this->index('embeddings'));

    $this->assertSame([
      'HEAD /embeddings',
      'POST /embeddings/_delete_by_query',
    ], $this->requests());
    $this->assertSame([
      ['term' => ['instance' => Project::LIIKENNE]],
    ], $this->body(1)['query']['bool']['filter']);
    $this->assertStringContainsString('conflicts=proceed', $this->history[1]['request']->getUri()->getQuery());
  }

  /**
   * The main instance clears only its own documents, too.
   */
  public function testClearOnMainInstanceDeletesOnlyOwnDocuments(): void {
    $this->setActiveProject(Project::ETUSIVU, EnvironmentEnum::Local);

    $this->backend()->deleteAllIndexItems($this->index('suggestions'), 'entity:suggested_topics');

    $this->assertSame([
      'HEAD /suggestions',
      'POST /suggestions/_delete_by_query',
    ], $this->requests());
    $this->assertSame([
      ['term' => ['parent_instance' => Project::ETUSIVU]],
      ['term' => ['search_api_datasource' => 'entity:suggested_topics']],
    ], $this->body(1)['query']['bool']['filter']);
  }

  /**
   * A shared index cannot be cleared when the active project is unknown.
   */
  public function testClearWithUnknownProjectFails(): void {
    $this->expectException(SearchApiException::class);
    $this->expectExceptionMessage('Cannot clear the shared index embeddings, because the active project is unknown.');

    $this->backend()->deleteAllIndexItems($this->index('embeddings'));
  }

  /**
   * A breaking field change on the main instance drops and creates the index.
   */
  public function testBreakingUpdateOnMainInstanceRecreatesIndex(): void {
    $this->setActiveProject(Project::ETUSIVU, EnvironmentEnum::Local);
    $index = $this->index('embeddings');
    $index->setStatus(TRUE);
    // New indexes count as reindexing until items get indexed.
    $this->container->get('state')->set('search_api.index.embeddings.has_reindexed', FALSE);

    // The previous version had a field that the new one drops.
    $original = clone $index;
    $original->addField($this->container->get('search_api.fields_helper')->createField($original, 'title', [
      'type' => 'string',
    ]));
    $index->setOriginal($original);
    $this->backend()->updateIndex($index);

    $this->assertTrue($index->isReindexing());
    // The mock reports every index as existing, so addIndex() stops after
    // its existence check instead of sending PUT /embeddings.
    $this->assertSame([
      'HEAD /embeddings',
      'HEAD /embeddings',
      'DELETE /embeddings',
      'HEAD /embeddings',
    ], $this->requests());
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
   * The Elasticsearch uuid of an index is read from its settings.
   */
  public function testGetElasticIndexUuid(): void {
    $this->history = [];
    $this->mockElasticsearchConnectorHttpClient([
      'embeddings' => ['settings' => ['index' => ['uuid' => 'abc123']]],
    ], $this->history);

    $this->assertSame('abc123', $this->backend()->getElasticIndexUuid($this->index('embeddings')));
    $this->assertCount(1, $this->history);
    $this->assertSame('/embeddings/_settings/index.uuid', $this->history[0]['request']->getUri()->getPath());
  }

  /**
   * A response without a uuid gives NULL.
   */
  public function testGetElasticIndexUuidWithoutUuid(): void {
    $this->assertNull($this->backend()->getElasticIndexUuid($this->index('embeddings')));
  }

  /**
   * The recorded requests as "METHOD /path".
   *
   * @return string[]
   *   The requests, in order.
   */
  private function requests(): array {
    return array_map(
      static fn (array $transaction): string => $transaction['request']->getMethod() . ' ' . $transaction['request']->getUri()->getPath(),
      $this->history,
    );
  }

  /**
   * The decoded JSON body of a recorded request.
   *
   * @return array<mixed>
   *   The body.
   */
  private function body(int $delta): array {
    return json_decode((string) $this->history[$delta]['request']->getBody(), TRUE, flags: JSON_THROW_ON_ERROR);
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
