<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_paragraphs_news_list\Kernel\Entity;

use Drupal\helfi_paragraphs_news_list\Entity\ExternalEntity\News;
use Drupal\helfi_paragraphs_news_list\Entity\NewsListLazyBuilder;
use Drupal\helfi_paragraphs_news_list\EventSubscriber\CacheResponseSubscriber;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\helfi_paragraphs_news_list\Kernel\KernelTestBase;
use Drupal\paragraphs\Entity\Paragraph;
use Elastic\Elasticsearch\ClientBuilder;

/**
 * Tests lazy builder.
 *
 * @group helfi_paragraphs_news_list
 */
class NewsListLazyBuilderTest extends KernelTestBase {

  use ApiTestTrait;

  /**
   * Tests ::build().
   */
  public function testBuild() : void {
    $realResponse = [
      'hits' => [
        'hits' => [
          [
            '_source' => [
              'uuid_langcode' => ['123'],
              'uuid' => ['uuid-123'],
              'title' => ['test title'],
              'field_news_groups' => ['Test groups'],
              'field_news_item_tags' => ['Test tag'],
              'field_news_neighbourhoods' => ['Test neighbourhood'],
              'url' => ['https://localhost'],
              'published_at' => [1234567],
              'short_title' => ['test shorttitle'],
            ],
          ],
        ],
      ],
    ];
    $mock = $this->createMockHttpClient([
      $this->createElasticsearchResponse([]),
      $this->createElasticsearchResponse($realResponse),
      $this->createElasticsearchResponse($realResponse),
    ]);
    $client = ClientBuilder::create()
      ->setHttpClient($mock)
      ->build();

    $this->container->set('helfi_platform_config.etusivu_elastic_client', $client);
    $sut = $this->container->get(NewsListLazyBuilder::class);
    assert($sut instanceof NewsListLazyBuilder);

    // Test non-existent paragraph.
    $this->assertEquals([], $sut->build('1'));

    $paragraph = Paragraph::create([
      'type' => 'news_list',
    ]);
    $paragraph->save();
    // Test empty response cache tags.
    $this->assertEquals([
      '#cache' => [
        'tags' => ['helfi_news_list_empty_results'],
        'max-age' => CacheResponseSubscriber::EMPTY_LIST_MAX_AGE,
      ],
      '#theme' => 'news_list__no_results',
    ], $sut->build($paragraph->id()));

    $paragraph = Paragraph::create([
      'type' => 'news_list',
      'field_news_limit' => 8,
      'field_news_list_title' => 'test title',
      'field_news_list_description' => 'test description',
    ]);
    $paragraph->save();

    $build = $sut->build($paragraph->id());
    $this->assertInstanceOf(News::class, $build[0]['#helfi_news']);
    $this->assertEquals([
      'helfi_news_view',
      'helfi_news:123',
      'config:external_entities.external_entity_type.helfi_news',
      'external_entity_type_values:helfi_news',
      'paragraph:' . $paragraph->id(),
      'external_entity_type_values',
      'entity_field_info',
    ], $build[0]['#cache']['tags']);
  }

}
