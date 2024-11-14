<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_paragraphs_news_list\Kernel\ExternalEntityStorage;

use Drupal\helfi_paragraphs_news_list\Entity\ExternalEntity\News;
use Elastic\Elasticsearch\Client;
use Prophecy\Argument;

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
    $client = $this->prophesize(Client::class);
    $client->search(Argument::any())
      ->shouldBeCalled()
      ->willReturn(
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
      );
    $client->search(Argument::any())
      ->shouldBeCalled();
    $sut = $this->getSut($client->reveal());
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
   * Tests query() method.
   */
  public function testQuery(): void {
    $client = $this->prophesize(Client::class);
    // Test no filters or sorts.
    $client->search([
      'index' => 'news',
      'body' => [
        'sort' => [],
        'query' => [],
      ],
    ])
      ->shouldBeCalled()
      ->willReturn($this->createElasticsearchResponse([]));
    // Test sort.
    $client->search([
      'index' => 'news',
      'body' => [
        'sort' => [
          'name' => ['order' => 'desc'],
        ],
        'query' => [],
      ],
    ])
      ->shouldBeCalled()
      ->willReturn($this->createElasticsearchResponse([]));
    // Test filters.
    $client->search([
      'index' => 'news',
      'body' => [
        'sort' => [],
        'query' => [
          'bool' => [
            'must' => [
              ['term' => ['name' => 'value']],
              [
                'bool' => [
                  'should' => [
                    ['term' => ['group' => 'group1']],
                    ['term' => ['group' => 'group2']],
                    ['term' => ['group' => 'group3']],
                  ],
                ],
              ],
              [
                'regexp' => [
                  'name' => ['value' => 'test.*', 'case_insensitive' => TRUE],
                ],
              ],
            ],
          ],
        ],
      ],
    ])
      ->shouldBeCalled()
      ->willReturn($this->createElasticsearchResponse([]));
    $this->getSut($client->reveal())->getQuery()->accessCheck(FALSE)->execute();
    $this->getSut($client->reveal())
      ->getQuery()
      ->accessCheck(FALSE)
      ->sort('name', 'DESC')
      ->execute();
    $this->getSut($client->reveal())
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('name', 'value')
      ->condition('group', ['group1', 'group2', 'group3'], 'IN')
      ->condition('name', 'test', 'CONTAINS')
      ->execute();
  }

}
