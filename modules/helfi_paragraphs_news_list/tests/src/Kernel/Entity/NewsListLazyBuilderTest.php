<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_paragraphs_news_list\Kernel\Entity;

use Drupal\helfi_paragraphs_news_list\Entity\ExternalEntity\News;
use Drupal\helfi_paragraphs_news_list\Entity\NewsListLazyBuilder;
use Drupal\helfi_paragraphs_news_list\EventSubscriber\CacheResponseSubscriber;
use Drupal\Tests\helfi_paragraphs_news_list\Kernel\KernelTestBase;
use Drupal\paragraphs\Entity\Paragraph;
use Elastic\Elasticsearch\Client;
use Prophecy\Argument;

/**
 * Tests lazy builder.
 *
 * @group helfi_paragraphs_news_list
 */
class NewsListLazyBuilderTest extends KernelTestBase {

  /**
   * Tests ::build().
   */
  public function testBuild() : void {
    $client = $this->prophesize(Client::class);
    $client->search(Argument::any())
      ->shouldBeCalled()
      ->willReturn(
        $this->createElasticsearchResponse([]),
        $this->createElasticsearchResponse([
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
        ]),
      );
    $this->container->set('helfi_paragraphs_news_list.elastic_client', $client->reveal());
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
      'paragraph:' . $paragraph->id(),
    ], $build[0]['#cache']['tags']);
  }

}
