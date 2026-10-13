<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\SearchAPI;

use Drupal\elasticsearch_connector\Analyser\AnalyserManager;
use Drupal\elasticsearch_connector\SearchAPI\BackendClient;
use Drupal\elasticsearch_connector\SearchAPI\DeleteParamBuilder;
use Drupal\elasticsearch_connector\SearchAPI\FieldMapper;
use Drupal\elasticsearch_connector\SearchAPI\IndexParamBuilder;
use Drupal\elasticsearch_connector\SearchAPI\Query\QueryParamBuilder;
use Drupal\elasticsearch_connector\SearchAPI\Query\QueryResultParser;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_api_base\Environment\ProjectMetadata;
use Drupal\helfi_platform_config\RestrictedSearchIndex;
use Drupal\helfi_platform_config\SearchAPI\RestrictedBackendClient;
use Drupal\helfi_platform_config\SearchAPI\RestrictedBackendClientFactory;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Utility\FieldsHelperInterface;
use Drupal\Tests\helfi_platform_config\Traits\ElasticTrait;
use Drupal\Tests\UnitTestCase;
use Elastic\Elasticsearch\Client;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Unit tests for the shared-index backend client.
 */
#[CoversClass(RestrictedBackendClient::class)]
#[CoversClass(RestrictedBackendClientFactory::class)]
#[Group('helfi_platform_config')]
final class RestrictedBackendClientTest extends UnitTestCase {

  use ElasticTrait;

  /**
   * Operations that delete, create, or remap an Elasticsearch index.
   *
   * @return array<string, array{string, string}>
   *   Method name and which collaborator proves the parent ran.
   */
  public static function schemaOperations(): array {
    return [
      'remove' => ['removeIndex', 'node'],
      'add' => ['addIndex', 'node'],
      'clear' => ['clearIndex', 'node'],
      'settings' => ['updateSettings', 'mapper'],
      'mapping' => ['updateFieldMapping', 'mapper'],
    ];
  }

  /**
   * Shared indexes skip schema changes on instances other than etusivu.
   */
  #[DataProvider('schemaOperations')]
  public function testSkipsSharedIndexOffEtusivu(string $method, string $signal): void {
    $history = [];
    $client = $this->client(isEtusivu: FALSE, signal: $signal, reachConnector: FALSE, history: $history);

    $client->{$method}($this->index('embeddings'));

    $this->assertSame([], $history);
  }

  /**
   * Etusivu still runs the connector for the shared indexes.
   */
  #[DataProvider('schemaOperations')]
  public function testRunsSharedIndexOnEtusivu(string $method, string $signal): void {
    $client = $this->client(isEtusivu: TRUE, signal: $signal, reachConnector: TRUE);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('connector called');
    $client->{$method}($this->index('suggestions'));
  }

  /**
   * Other indexes still run the connector on instances other than etusivu.
   */
  #[DataProvider('schemaOperations')]
  public function testRunsOtherIndexesOffEtusivu(string $method, string $signal): void {
    $client = $this->client(isEtusivu: FALSE, signal: $signal, reachConnector: TRUE);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('connector called');
    $client->{$method}($this->index('news'));
  }

  /**
   * A deleted index is passed as an id string to removeIndex().
   */
  public function testSkipsRemovedSharedIndexId(): void {
    $history = [];
    $client = $this->client(isEtusivu: FALSE, signal: 'node', reachConnector: FALSE, history: $history);

    $client->removeIndex('embeddings');

    $this->assertSame([], $history);
  }

  /**
   * Document indexing and deletion stay on the connector client.
   */
  public function testDocumentOperationsStayOnParent(): void {
    $indexItems = new \ReflectionMethod(RestrictedBackendClient::class, 'indexItems');
    $deleteItems = new \ReflectionMethod(RestrictedBackendClient::class, 'deleteItems');

    $this->assertSame(BackendClient::class, $indexItems->getDeclaringClass()->getName());
    $this->assertSame(BackendClient::class, $deleteItems->getDeclaringClass()->getName());
  }

  /**
   * The factory returns the guarding client and passes the shared-index check.
   */
  public function testFactoryUsesTheGuardingClient(): void {
    $fieldMapper = $this->createMock(FieldMapper::class);
    $fieldMapper->expects($this->never())->method('mapFieldParams');
    $factory = new RestrictedBackendClientFactory(
      $this->createMock(QueryParamBuilder::class),
      $this->createMock(QueryResultParser::class),
      $this->createMock(DeleteParamBuilder::class),
      $this->createMock(IndexParamBuilder::class),
      $this->createMock(FieldsHelperInterface::class),
      $fieldMapper,
      $this->createMock(LoggerInterface::class),
      $this->analyserManager(),
      $this->createMock(EventDispatcherInterface::class),
      $this->restrictedSearchIndex(FALSE),
    );

    $history = [];
    $client = $factory->create($this->elasticsearchClient(FALSE, $history), [
      'prefix' => '',
      'suffix' => '',
    ]);

    $this->assertInstanceOf(RestrictedBackendClient::class, $client);
    $client->updateFieldMapping($this->index('embeddings'));
    $this->assertSame([], $history);
  }

  /**
   * Builds a client whose connector call is either refused or thrown.
   */
  private function client(bool $isEtusivu, string $signal, bool $reachConnector, array &$history = []): RestrictedBackendClient {
    $fieldMapper = $this->createMock(FieldMapper::class);
    if ($signal === 'mapper') {
      if ($reachConnector) {
        $fieldMapper->expects($this->once())
          ->method('mapFieldParams')
          ->willThrowException(new \RuntimeException('connector called'));
      }
      else {
        $fieldMapper->expects($this->never())->method('mapFieldParams');
      }
    }

    return new RestrictedBackendClient(
      $this->createMock(QueryParamBuilder::class),
      $this->createMock(QueryResultParser::class),
      $this->createMock(DeleteParamBuilder::class),
      $this->createMock(IndexParamBuilder::class),
      $this->createMock(FieldsHelperInterface::class),
      $fieldMapper,
      $this->createMock(LoggerInterface::class),
      $this->elasticsearchClient($signal === 'node' && $reachConnector, $history),
      $this->analyserManager(),
      $this->createMock(EventDispatcherInterface::class),
      ['prefix' => '', 'suffix' => ''],
      $this->restrictedSearchIndex($isEtusivu),
    );
  }

  /**
   * Builds an analyser manager without running its plugin discovery.
   */
  private function analyserManager(): AnalyserManager {
    return (new \ReflectionClass(AnalyserManager::class))->newInstanceWithoutConstructor();
  }

  /**
   * Builds an Elasticsearch client that fails when a request is sent.
   *
   * @param bool $reachConnector
   *   Whether a request should fail with the connector exception.
   * @param array<int, array<string, mixed>> $history
   *   Outgoing requests are appended here.
   */
  private function elasticsearchClient(bool $reachConnector, array &$history = []): Client {
    $responses = $reachConnector ? [new \RuntimeException('connector called')] : [];
    return $this->createMockElasticsearchClient($responses, $history);
  }

  /**
   * Builds the shared-index guard for etusivu or another instance.
   */
  private function restrictedSearchIndex(bool $isEtusivu): RestrictedSearchIndex {
    $resolver = $this->createMock(EnvironmentResolverInterface::class);
    $resolver->method('getActiveProject')->willReturn(new Project(
      $isEtusivu ? Project::ETUSIVU : Project::LIIKENNE,
      new ProjectMetadata('https://github.com/city-of-helsinki/drupal-helfi-etusivu'),
    ));
    return new RestrictedSearchIndex($resolver);
  }

  /**
   * Builds an index with the given id.
   */
  private function index(string $id): IndexInterface&MockObject {
    $index = $this->createMock(IndexInterface::class);
    $index->method('id')->willReturn($id);
    return $index;
  }

}
