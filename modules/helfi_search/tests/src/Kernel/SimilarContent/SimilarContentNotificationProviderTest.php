<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_search\Kernel\SimilarContent;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Database\Connection;
use Drupal\entity_test\Entity\EntityTestMulRevPub;
use Drupal\helfi_platform_config\MultisiteSearch;
use Drupal\helfi_search\SimilarContent\EntityNotifications\SimilarContentNotificationProvider;
use Drupal\helfi_search\Pipeline\Chunk;
use Drupal\helfi_search\SimilarContent\SimilarContentFinder;
use Drupal\Tests\helfi_platform_config\Kernel\KernelTestBase;
use Drupal\Tests\helfi_platform_config\Traits\ElasticTrait;
use Drupal\Tests\helfi_search\Traits\EmbeddingStoreTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\NullLogger;

/**
 * Tests the similar content notification provider.
 */
#[Group('helfi_search')]
#[RunTestsInSeparateProcesses]
class SimilarContentNotificationProviderTest extends KernelTestBase {

  use ElasticTrait;
  use EmbeddingStoreTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'entity_test',
    'search_api',
    'helfi_search',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('entity_test_mulrevpub');
    $this->installSchema('helfi_search', ['helfi_search_chunk']);
  }

  /**
   * Tests that similar documents are counted.
   */
  public function testSimilarContent(): void {
    $entity = EntityTestMulRevPub::create();
    $entity->save();
    $this->fillChunks($entity, [new Chunk(text: 'First'), new Chunk(text: 'Second')]);

    $history = [];
    $provider = $this->createProvider([
      $this->createElasticsearchResponse([
        'hits' => ['hits' => [['_id' => 'entity:node/1:en'], ['_id' => 'entity:node/2:en']]],
      ]),
    ], $history);

    $cacheability = new CacheableMetadata();
    $notifications = $provider->getNotifications($entity, $cacheability);

    $this->assertCount(1, $notifications);
    $this->assertEquals('2 pages with similar content', (string) $notifications[0]->label);

    // The link opens the similar content in a dialog.
    $url = $notifications[0]->url;
    $this->assertSame('helfi_search.similar_content', $url->getRouteName());
    $this->assertEquals([
      'entity_type' => 'entity_test_mulrevpub',
      'entity' => $entity->id(),
      'langcode' => 'en',
    ], $url->getRouteParameters());
    $attributes = $url->getOption('attributes');
    $this->assertContains('use-ajax', $attributes['class']);
    $this->assertSame('modal', $attributes['data-dialog-type']);
  }

  /**
   * Tests that no notification is shown without similar content.
   */
  public function testNoSimilarContent(): void {
    $entity = EntityTestMulRevPub::create();
    $entity->save();
    $this->fillChunks($entity, [new Chunk(text: 'First')]);

    $provider = $this->createProvider([
      $this->createElasticsearchResponse([
        'hits' => ['hits' => []],
      ]),
    ]);

    $this->assertEmpty($provider->getNotifications($entity, new CacheableMetadata()));
  }

  /**
   * Builds the provider with a mocked Elasticsearch client.
   *
   * @param array<int, \Psr\Http\Message\ResponseInterface|\Throwable> $responses
   *   Elasticsearch responses.
   * @param array<int, array<string, mixed>> $history
   *   Outgoing requests are appended here.
   */
  private function createProvider(array $responses, array &$history = []): SimilarContentNotificationProvider {
    return new SimilarContentNotificationProvider(
      new SimilarContentFinder(
        $this->container->get(Connection::class),
        $this->container->get(MultisiteSearch::class),
        $this->createMockElasticsearchClient($responses, $history),
      ),
      new NullLogger(),
    );
  }

}
