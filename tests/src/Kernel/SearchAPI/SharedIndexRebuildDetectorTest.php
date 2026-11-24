<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel\SearchAPI;

use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_platform_config\SearchAPI\SharedIndexRebuildDetector;
use Drupal\search_api\Entity\Server;
use Drupal\Tests\helfi_api_base\Traits\EnvironmentResolverTrait;
use Drupal\Tests\helfi_platform_config\Kernel\KernelTestBase;
use Drupal\Tests\helfi_platform_config\Traits\ElasticKernelTrait;
use Drupal\Tests\helfi_platform_config\Traits\SharedSearchIndexTrait;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Http\Message\RequestInterface;

/**
 * Tests that recreated shared indexes are reindexed on other instances.
 */
#[Group('helfi_platform_config')]
#[RunTestsInSeparateProcesses]
final class SharedIndexRebuildDetectorTest extends KernelTestBase {

  use ElasticKernelTrait;
  use EnvironmentResolverTrait;
  use SharedSearchIndexTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'elasticsearch_connector',
    'entity_test',
    'language',
    'search_api',
    'search_api_test',
    'user',
  ];

  /**
   * The uuid Elasticsearch reports for every index, or NULL for a 404.
   */
  private ?string $uuid = 'first';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setUpSearchApi();
    $this->mockElasticsearch();

    Server::create([
      'id' => 'elastic',
      'name' => 'Elastic',
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
          'synonyms' => [],
        ],
      ],
    ])->save();
  }

  /**
   * A new uuid marks the items of a shared index for reindexing.
   */
  public function testRecreatedIndexIsReindexed(): void {
    $this->setActiveProject(Project::ASUMINEN, EnvironmentEnum::Local);
    $this->createIndex('embeddings', server: 'elastic');
    $this->createIndexedItems(2);

    // The first run only stores the uuid.
    $this->detector()->reindexRecreatedIndexes();
    $this->assertSame(0, $this->remainingItems('embeddings'));
    $this->assertSame(['embeddings' => 'first'], $this->storedUuids());

    // Same uuid, nothing to do.
    $this->detector()->reindexRecreatedIndexes();
    $this->assertSame(0, $this->remainingItems('embeddings'));

    // Etusivu cleared the index.
    $this->uuid = 'second';
    $this->detector()->reindexRecreatedIndexes();
    $this->assertSame(2, $this->remainingItems('embeddings'));
    $this->assertSame(['embeddings' => 'second'], $this->storedUuids());
  }

  /**
   * A missing index is checked again later, without losing the old uuid.
   */
  public function testMissingIndexKeepsStoredUuid(): void {
    $this->setActiveProject(Project::ASUMINEN, EnvironmentEnum::Local);
    $this->createIndex('embeddings', server: 'elastic');
    $this->createIndexedItems(2);
    $this->detector()->reindexRecreatedIndexes();

    // Between the delete and the create on the main instance.
    $this->uuid = NULL;
    $this->detector()->reindexRecreatedIndexes();
    $this->assertSame(0, $this->remainingItems('embeddings'));
    $this->assertSame(['embeddings' => 'first'], $this->storedUuids());

    $this->uuid = 'second';
    $this->detector()->reindexRecreatedIndexes();
    $this->assertSame(2, $this->remainingItems('embeddings'));
  }

  /**
   * The main instance and indexes that are not shared are not checked.
   */
  public function testSkipsMainInstanceAndOtherIndexes(): void {
    $this->setActiveProject(Project::ETUSIVU, EnvironmentEnum::Local);
    $this->createIndex('embeddings', server: 'elastic');
    $this->createIndex('news', server: 'elastic');
    $this->createIndexedItems(2);

    $this->detector()->reindexRecreatedIndexes();
    $this->uuid = 'second';
    $this->detector()->reindexRecreatedIndexes();

    $this->assertSame(0, $this->remainingItems('embeddings'));
    $this->assertSame(0, $this->remainingItems('news'));
    $this->assertSame([], $this->storedUuids());
  }

  /**
   * Disabled indexes and other backends are not checked.
   */
  public function testSkipsDisabledIndexAndOtherBackends(): void {
    $this->setActiveProject(Project::LIIKENNE, EnvironmentEnum::Local);
    $this->createIndex('embeddings', FALSE, 'elastic');
    $this->createIndex('suggestions');

    $this->detector()->reindexRecreatedIndexes();

    $this->assertSame([], $this->storedUuids());
  }

  /**
   * Cron runs the check.
   */
  public function testCron(): void {
    $this->setActiveProject(Project::ASUMINEN, EnvironmentEnum::Local);
    $this->createIndex('embeddings', server: 'elastic');

    $this->container->get('cron')->run();

    $this->assertSame(['embeddings' => 'first'], $this->storedUuids());
  }

  /**
   * The detector from the container.
   */
  private function detector(): SharedIndexRebuildDetector {
    return $this->container->get(SharedIndexRebuildDetector::class);
  }

  /**
   * The uuids the detector stored.
   *
   * @return array<string, string>
   *   Uuids keyed by index id.
   */
  private function storedUuids(): array {
    return $this->container->get('state')->get(SharedIndexRebuildDetector::STATE_KEY, []);
  }

  /**
   * Answers every Elasticsearch request with the current uuid.
   *
   * Settings requests get the uuid in the index settings, or a 404 when the
   * uuid is NULL. Other requests get an empty acknowledgement.
   */
  private function mockElasticsearch(): void {
    $stack = new HandlerStack(function (RequestInterface $request) {
      $path = $request->getUri()->getPath();
      if (!str_contains($path, '/_settings')) {
        return Create::promiseFor($this->createElasticsearchResponse(['acknowledged' => TRUE]));
      }
      $indexId = explode('/', trim($path, '/'))[0];
      if ($this->uuid === NULL) {
        return Create::promiseFor(new Response(404, $this->elasticsearchResponseHeaders(), (string) json_encode([
          'error' => ['type' => 'index_not_found_exception'],
          'status' => 404,
        ])));
      }
      return Create::promiseFor($this->createElasticsearchResponse([
        $indexId => ['settings' => ['index' => ['uuid' => $this->uuid]]],
      ]));
    });
    $this->container->set('http_client', new HttpClient(['handler' => $stack]));
  }

}
