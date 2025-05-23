<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_paragraphs_news_list\Kernel\ExternalEntityStorage;

use Drupal\helfi_paragraphs_news_list\Entity\ExternalEntity\Term;
use Elastic\Elasticsearch\Client;
use Prophecy\Argument;

/**
 * A base class for term storage tests.
 */
abstract class TermStorageClientTestBase extends StorageClientTestBase {

  /**
   * Gets the VID.
   *
   * @return string
   *   The VID.
   */
  abstract protected function getVid(): string;

  /**
   * Tests load multiple.
   */
  public function testLoadMultiple() : void {
    $client = $this->prophesize(Client::class);
    $client->search(Argument::any())
      ->shouldBeCalled()
      ->willReturn($this->createElasticsearchResponse([]), $this->createElasticsearchResponse([
        'hits' => [
          'hits' => [
            // Working item.
            [
              '_source' => [
                'uuid_langcode' => ['123'],
                'uuid' => ['uuid-123'],
                'name' => ['test title'],
                'tid' => [123],
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
      ]));
    $client->search(Argument::any())
      ->shouldBeCalled();
    $sut = $this->getSut($client->reveal());
    $this->assertEmpty($sut->loadMultiple([123]));

    $values = $sut->loadMultiple([321, 321]);
    $this->assertCount(1, $values);
    $entity = $values[123];

    $this->assertInstanceOf(Term::class, $entity);
    $this->assertEquals('123', $entity->id());
    $this->assertEquals('uuid-123', $entity->uuid());
    $this->assertEquals('test title', $entity->label());
    $this->assertEquals(123, $entity->getTid());
  }

  /**
   * Tests query() method.
   */
  public function testQuery(): void {
    $client = $this->prophesize(Client::class);
    // Test no filters or sorts.
    $client->search([
      'index' => 'news_terms',
      'body' => [
        'sort' => [],
        'query' => [
          'bool' => [
            'must' => [
              ['term' => ['vid' => $this->getVid()]],
            ],
          ],
        ],
      ],
    ])
      ->shouldBeCalled()
      ->willReturn($this->createElasticsearchResponse([]));
    // Test sort.
    $client->search([
      'index' => 'news_terms',
      'body' => [
        'sort' => [
          'name' => ['order' => 'desc'],
        ],
        'query' => [
          'bool' => [
            'must' => [
              ['term' => ['vid' => $this->getVid()]],
            ],
          ],
        ],
      ],
    ])
      ->shouldBeCalled()
      ->willReturn($this->createElasticsearchResponse([]));
    // Test filters.
    $client->search([
      'index' => 'news_terms',
      'body' => [
        'sort' => [],
        'query' => [
          'bool' => [
            'must' => [
              ['term' => ['name' => 'value']],
              [
                'bool' => [
                  'should' => [
                    ['term' => ['tags' => 1]],
                    ['term' => ['tags' => 2]],
                    ['term' => ['tags' => 3]],
                  ],
                ],
              ],
              [
                'regexp' => [
                  'name' => ['value' => 'test.*', 'case_insensitive' => TRUE],
                ],
              ],
              ['term' => ['vid' => $this->getVid()]],
            ],
          ],
        ],
      ],
    ])
      ->shouldBeCalled()
      ->willReturn($this->createElasticsearchResponse([]));
    $this->getSut($client->reveal())
      ->getQuery()
      ->accessCheck(FALSE)
      ->execute();
    $this->getSut($client->reveal())
      ->getQuery()
      ->accessCheck(FALSE)
      ->sort('name', 'DESC')
      ->execute();
    $this->getSut($client->reveal())
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('name', 'value')
      ->condition('tags', [1, 2, 3], 'IN')
      ->condition('name', 'test', 'CONTAINS')
      ->execute();
  }

}
