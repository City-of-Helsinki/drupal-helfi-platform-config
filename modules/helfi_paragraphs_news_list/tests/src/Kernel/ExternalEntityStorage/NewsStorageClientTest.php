<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_paragraphs_news_list\Kernel\ExternalEntityStorage;

use Drupal\helfi_paragraphs_news_list\Entity\ExternalEntity\News;

/**
 * Tests news storage client.
 *
 * @group helfi_paragraphs_news_list
 */
class NewsStorageClientTest extends StorageClientTestBase {

  /**
   * {@inheritdoc}
   */
  protected function getStorageName(): string {
    return 'helfi_news';
  }

  /**
   * Tests load multiple.
   */
  public function testLoadMultiple() : void {
    $container = [];
    $responses = [
      $this->createElasticsearchResponse([]),
      $this->createElasticsearchResponse([
        'hits' => [
          'hits' => [
            // Working item.
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
            // Missing uuid_langcode field.
            [
              '_source' => [
                'uuid' => 'uuid-321',
              ],
            ],
          ],
        ],
      ]),
    ];
    $sut = $this->getSut($container, $responses);
    $this->assertEmpty($sut->loadMultiple([123]));

    $values = $sut->loadMultiple([321, 321]);
    $this->assertCount(1, $values);
    $entity = $values[123];

    $this->assertInstanceOf(News::class, $entity);
    $this->assertEquals('123', $entity->id());
    $this->assertEquals('uuid-123', $entity->uuid());
    $this->assertEquals('test title', $entity->label());
    $this->assertEquals('https://localhost', $entity->getNodeUrl());
    $this->assertEquals(1234567, $entity->getPublishedAt());
    $this->assertEquals('test shorttitle', $entity->getShortTitle());
  }

  /**
   * Test the query.
   */
  public function testQuery(): void {
    // Test no filters or sorts.
    $expected = [
      'sort' => [],
      'query' => [],
    ];

    $container = [];
    $this->getSut($container, [$this->createElasticsearchResponse([])])
      ->getQuery()
      ->accessCheck(FALSE)
      ->execute();

    $this->assertHttpHistoryContainer($expected, $container);
  }

  /**
   * Test the sort query.
   */
  public function testSort(): void {
    $expected = [
      'sort' => [
        'title' => ['order' => 'desc'],
      ],
      'query' => [],
    ];

    $container = [];
    $this->getSut($container, [$this->createElasticsearchResponse([])])
      ->getQuery()
      ->accessCheck(FALSE)
      ->sort('title', 'DESC')
      ->execute();

    $this->assertHttpHistoryContainer($expected, $container);
  }

  /**
   * Test the filter query.
   */
  public function testFilter(): void {
    $expected = [
      'sort' => [],
      'query' => [
        'bool' => [
          'must' => [
            ['term' => ['title' => 'value']],
            [
              'bool' => [
                'should' => [
                  ['term' => ['news_groups' => 'group1']],
                  ['term' => ['news_groups' => 'group2']],
                  ['term' => ['news_groups' => 'group3']],
                ],
              ],
            ],
            [
              'regexp' => [
                'title' => ['value' => 'test.*', 'case_insensitive' => TRUE],
              ],
            ],
          ],
        ],
      ],
    ];

    $container = [];
    $this->getSut($container, [$this->createElasticsearchResponse([])])
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('title', 'value')
      ->condition('news_groups', ['group1', 'group2', 'group3'], 'IN')
      ->condition('title', 'test', 'CONTAINS')
      ->execute();

    $this->assertHttpHistoryContainer($expected, $container);
  }

}
