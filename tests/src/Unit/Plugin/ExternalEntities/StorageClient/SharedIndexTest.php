<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\Plugin\ExternalEntities\StorageClient;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Utility\Token;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_platform_config\Plugin\ExternalEntities\StorageClient\SharedIndex;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\helfi_api_base\Traits\EnvironmentResolverTrait;
use Drupal\Tests\helfi_platform_config\Traits\ElasticTrait;
use Drupal\Tests\UnitTestCase;
use Elastic\Elasticsearch\ClientBuilder;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Tests the shared index storage client.
 */
#[CoversClass(SharedIndex::class)]
#[Group('helfi_platform_config')]
final class SharedIndexTest extends UnitTestCase {

  use ApiTestTrait;
  use ElasticTrait;
  use EnvironmentResolverTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $container = new ContainerBuilder();
    $container->set('event_dispatcher', new EventDispatcher());
    \Drupal::setContainer($container);
  }

  /**
   * Tests Drupal id filters are mapped to Elasticsearch _id.
   */
  public function testTransliterateDrupalIdFilter(): void {
    $history = [];
    $result = $this->createSut($history)->transliterateDrupalFilters([
      ['field' => 'id', 'operator' => '=', 'value' => 'site_etusivu/entity:node/1:en'],
    ]);

    $this->assertSame([
      [
        'field' => '_id',
        'operator' => '=',
        'value' => ['site_etusivu/entity:node/1:en'],
      ],
    ], $result['source']);
  }

  /**
   * Tests loadMultiple with no ids does not query Elasticsearch.
   */
  public function testLoadMultipleWithoutIds(): void {
    $history = [];
    $sut = $this->createSut($history);
    $this->assertSame([], $sut->loadMultiple(NULL));
    $this->assertSame([], $sut->loadMultiple([]));
    $this->assertSame([], $history);
  }

  /**
   * Tests a free-text search query.
   */
  public function testQuerySourceTextSearch(): void {
    $history = [];
    $this->createSut($history)->querySource([
      ['field' => 'search', 'value' => 'library'],
    ], [], 5, 20);

    $body = $this->getRequestBody($history);
    $this->assertSame(20, $body['size']);
    $this->assertSame(5, $body['from']);
    $this->assertSame([
      'query' => 'library',
      'type' => 'best_fields',
      'fields' => ['label', 'metatag_title', 'url'],
    ], $body['query']['bool']['must'][0]['multi_match']);
    $this->assertSame([
      'bool' => [
        'must' => [
          ['term' => ['instance' => 'etusivu']],
          ['term' => ['search_api_language' => 'fi']],
        ],
      ],
    ], $body['query']['bool']['must_not'][0]);
    $this->assertContains('etusivu', $body['query']['bool']['filter'][0]['terms']['instance']);
  }

  /**
   * Tests looking up an existing canonical path.
   */
  public function testQuerySourceCanonicalPath(): void {
    $history = [];
    $this->createSut($history)->querySource([
      [
        'field' => 'search',
        'value' => '/fi/helfi-multisite-content/site_etusivu/entity-node/8420-en',
      ],
    ]);

    $body = $this->getRequestBody($history);
    $this->assertArrayNotHasKey('must', $body['query']['bool']);
    $this->assertArrayNotHasKey('must_not', $body['query']['bool']);
    $this->assertSame(
      ['site_etusivu/entity:node/8420:en'],
      $body['query']['bool']['filter'][0]['terms']['_id'],
    );
  }

  /**
   * Tests filtering by Elasticsearch document ids.
   */
  public function testQuerySourceById(): void {
    $history = [];
    $hits = $this->createSut($history)->loadMultiple(['site_etusivu/entity:node/1:en', '2']);

    $body = $this->getRequestBody($history);
    $this->assertSame(2, $body['size']);
    $this->assertSame(
      ['site_etusivu/entity:node/1:en', '2'],
      $body['query']['bool']['filter'][0]['terms']['_id'],
    );
    $this->assertArrayHasKey('site_etusivu/entity:node/1:en', $hits);
  }

  /**
   * Tests ping when the embeddings index exists.
   */
  public function testPingSuccess(): void {
    $history = [];
    $this->assertTrue($this->createSut($history, [$this->createElasticsearchResponse([])])->ping());
  }

  /**
   * Tests ping when Elasticsearch is unavailable.
   */
  public function testPingFailure(): void {
    $history = [];
    $this->assertFalse($this->createSut($history, [new Response(500)])->ping());
  }

  /**
   * Tests that Elasticsearch errors are caught.
   */
  public function testQuerySourceException(): void {
    $history = [];
    $this->assertSame([], $this->createSut($history, [new Response(500)])->querySource());
  }

  /**
   * Creates the storage client with a mocked Elasticsearch HTTP client.
   *
   * @param array<mixed> $history
   *   Guzzle history container.
   * @param \Psr\Http\Message\ResponseInterface[] $responses
   *   Mocked responses.
   */
  private function createSut(array &$history, array $responses = []): SharedIndex {
    if ($responses === []) {
      $responses = [
        $this->createElasticsearchResponse([
          'hits' => [
            'hits' => [
              ['_id' => 'site_etusivu/entity:node/1:en', '_source' => ['label' => ['Test']]],
            ],
          ],
        ]),
      ];
    }

    $client = ClientBuilder::create()
      ->setHttpClient($this->createMockHistoryMiddlewareHttpClient($history, $responses))
      ->build();

    $language = $this->createMock(LanguageInterface::class);
    $language->method('getId')->willReturn('fi');
    $language_manager = $this->createMock(LanguageManagerInterface::class);
    $language_manager->method('getCurrentLanguage')
      ->with(LanguageInterface::TYPE_CONTENT)
      ->willReturn($language);

    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($this->createMock(LoggerChannelInterface::class));

    $sut = new SharedIndex(
      [],
      'helfi_shared_index',
      ['id' => 'helfi_shared_index', 'label' => 'Shared index'],
      $this->getStringTranslationStub(),
      $logger_factory,
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(EntityFieldManagerInterface::class),
      $this->createMock(Token::class),
    );

    foreach ([
      'elasticsearchClient' => $client,
      'environmentResolver' => $this->getEnvironmentResolver(Project::ETUSIVU, EnvironmentEnum::Local),
      'languageManager' => $language_manager,
    ] as $property => $value) {
      $reflection = new \ReflectionProperty(SharedIndex::class, $property);
      $reflection->setValue($sut, $value);
    }

    return $sut;
  }

  /**
   * Decodes the Elasticsearch request body from Guzzle history.
   *
   * @param array<mixed> $history
   *   Guzzle history container.
   *
   * @return array<string, mixed>
   *   Decoded JSON body.
   */
  private function getRequestBody(array $history): array {
    $this->assertNotEmpty($history);
    $this->assertIsArray($history[0]);
    $this->assertInstanceOf(RequestInterface::class, $history[0]['request']);
    $body = json_decode((string) $history[0]['request']->getBody(), TRUE);
    $this->assertIsArray($body);
    return $body;
  }

}
