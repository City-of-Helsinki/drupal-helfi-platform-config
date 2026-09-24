<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel\Plugin\ExternalEntities\StorageClient;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\external_entities\Entity\ExternalEntityType;
use Drupal\external_entities\ExternalEntityStorage;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_platform_config\Entity\ExternalEntity\MultisiteContent;
use Drupal\helfi_platform_config\MultisiteContentId;
use Drupal\helfi_platform_config\Plugin\ExternalEntities\StorageClient\SharedIndex;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\helfi_api_base\Traits\EnvironmentResolverTrait;
use Drupal\Tests\helfi_platform_config\Kernel\KernelTestBase;
use Drupal\Tests\helfi_platform_config\Traits\ElasticTrait;
use Elastic\Elasticsearch\ClientBuilder;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Http\Message\RequestInterface;

/**
 * Tests the shared index storage client through entity storage.
 */
#[CoversClass(SharedIndex::class)]
#[Group('helfi_platform_config')]
#[RunTestsInSeparateProcesses]
final class SharedIndexTest extends KernelTestBase {

  use ApiTestTrait;
  use ElasticTrait;
  use EnvironmentResolverTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity',
    'external_entities',
    'field',
    'language',
    'serialization',
    'system',
    'text',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->container
      ->get(RouteProviderInterface::class)
      ->getAllRoutes();

    $this->installConfig(['system', 'external_entities']);
    $this->installEntitySchema('user');
    $this->setActiveProject(Project::ETUSIVU, EnvironmentEnum::Local);

    $history = [];
    $this->container->set(
      'helfi_platform_config.etusivu_elastic_client',
      ClientBuilder::create()
        ->setHttpClient($this->createMockHistoryMiddlewareHttpClient($history))
        ->build(),
    );

    $module_path = $this->container
      ->get('extension.list.module')
      ->getPath('helfi_platform_config');
    $values = Yaml::decode((string) file_get_contents(
      $module_path . '/config/install/external_entities.external_entity_type.helfi_multisite_content.yml',
    ));
    unset($values['uuid']);
    ExternalEntityType::create($values)->save();

    $this->installEntitySchema(MultisiteContentId::ENTITY_TYPE_ID);
    $this->container->get('entity_type.manager')->clearCachedDefinitions();
    $this->container->get('entity_type.bundle.info')->clearCachedBundles();
  }

  /**
   * Tests that Elasticsearch errors are caught.
   */
  public function testRequestException(): void {
    $history = [];
    $sut = $this->getStorage($history, [
      new Response(500),
      new Response(500),
    ]);
    $this->assertEmpty($sut->loadMultiple(['site_asuminen/entity:node/1:fi']));
    $this->assertEmpty($sut->getQuery()->accessCheck(FALSE)->execute());
  }

  /**
   * Tests loading mapped hits from Elasticsearch.
   */
  public function testLoadMultiple(): void {
    $id = 'site_asuminen/entity:node/1:fi';
    $history = [];
    $storage = $this->getStorage($history, [
      $this->createElasticsearchResponse([]),
      $this->createElasticsearchResponse([
        'hits' => [
          'hits' => [
            [
              '_id' => $id,
              '_source' => [
                'label' => ['Housing page'],
                'url' => ['https://www.hel.fi/fi/housing'],
                'search_api_language' => ['fi'],
              ],
            ],
            [
              '_id' => 'site_asuminen/entity:node/2:fi',
              '_source' => [
                'url' => ['https://www.hel.fi/fi/other'],
              ],
            ],
          ],
        ],
      ]),
    ]);

    $this->assertEmpty($storage->loadMultiple([$id]));

    $entities = $storage->loadMultiple([$id, 'site_asuminen/entity:node/2:fi']);
    $this->assertCount(2, $entities);
    $entity = $entities[$id];
    $this->assertInstanceOf(MultisiteContent::class, $entity);
    $this->assertSame($id, $entity->id());
    $this->assertSame('Housing page', $entity->label());
    $this->assertSame('https://www.hel.fi/fi/housing', $entity->get('entity_url')->value);

    $body = $this->getRequestBody($history[1]['request']);
    $this->assertSame(2, $body['size']);
    $this->assertSame(
      [$id, 'site_asuminen/entity:node/2:fi'],
      $body['query']['bool']['filter'][0]['terms']['_id'],
    );
  }

  /**
   * Tests a free-text title query.
   */
  public function testTitleQuery(): void {
    $history = [];
    $this->getStorage($history, [$this->createElasticsearchResponse([])])
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('title', 'library', 'CONTAINS')
      ->execute();

    $body = $this->getRequestBody($history[0]['request']);
    $this->assertSame(10, $body['size']);
    $this->assertArrayNotHasKey('from', $body);
    $this->assertSame([
      'query' => 'library',
      'type' => 'best_fields',
      'fields' => ['label', 'metatag_title', 'url'],
    ], $body['query']['bool']['must'][0]['multi_match']);
    $this->assertSame([
      'bool' => [
        'must' => [
          ['term' => ['instance' => 'etusivu']],
          ['term' => ['search_api_language' => 'en']],
        ],
      ],
    ], $body['query']['bool']['must_not'][0]);
    $this->assertContains('etusivu', $body['query']['bool']['filter'][0]['terms']['instance']);
  }

  /**
   * Tests looking up an existing canonical path via the title filter.
   */
  public function testCanonicalPathQuery(): void {
    $history = [];
    $this->getStorage($history, [$this->createElasticsearchResponse([])])
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('title', '/fi/helfi-multisite-content/site_etusivu/entity-node/8420-en', 'CONTAINS')
      ->execute();

    $body = $this->getRequestBody($history[0]['request']);
    $this->assertArrayNotHasKey('must', $body['query']['bool']);
    $this->assertArrayNotHasKey('must_not', $body['query']['bool']);
    $this->assertSame(
      ['site_etusivu/entity:node/8420:en'],
      $body['query']['bool']['filter'][0]['terms']['_id'],
    );
  }

  /**
   * Creates entity storage with a mocked Elasticsearch HTTP client.
   *
   * @param array<mixed> $history
   *   Guzzle history container.
   * @param \Psr\Http\Message\ResponseInterface[] $responses
   *   Mocked responses.
   */
  private function getStorage(array &$history, array $responses): ExternalEntityStorage {
    $this->container->set(
      'helfi_platform_config.etusivu_elastic_client',
      ClientBuilder::create()
        ->setHttpClient($this->createMockHistoryMiddlewareHttpClient($history, $responses))
        ->build(),
    );
    $this->container->get('entity_type.manager')->clearCachedDefinitions();

    $storage = $this->container
      ->get(EntityTypeManagerInterface::class)
      ->getStorage(MultisiteContentId::ENTITY_TYPE_ID);
    $this->assertInstanceOf(ExternalEntityStorage::class, $storage);
    return $storage;
  }

  /**
   * Decodes an Elasticsearch request body.
   *
   * @return array<string, mixed>
   *   Decoded JSON body.
   */
  private function getRequestBody(RequestInterface $request): array {
    $body = json_decode((string) $request->getBody(), TRUE);
    $this->assertIsArray($body);
    return $body;
  }

}
