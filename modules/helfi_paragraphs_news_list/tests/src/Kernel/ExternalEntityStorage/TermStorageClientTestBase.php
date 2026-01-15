<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_paragraphs_news_list\Kernel\ExternalEntityStorage;

use Drupal\helfi_paragraphs_news_list\Entity\ExternalEntity\Term;

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
      ]),
    ];
    $sut = $this->getSut($container, $responses);
    $this->assertEmpty($sut->loadMultiple([123]));

    $values = $sut->loadMultiple([123, 321]);
    $this->assertCount(1, $values);
    $entity = $values[123];

    $this->assertInstanceOf(Term::class, $entity);
    $this->assertEquals('123', $entity->id());
    $this->assertEquals('uuid-123', $entity->uuid());
    $this->assertEquals('test title', $entity->label());
    $this->assertEquals(123, $entity->getTid());
  }

  /**
   * Test the query.
   */
  public function testQuery(): void {
    $expected = [
      'sort' => [],
      'query' => [
        'bool' => [
          'must' => [
            ['term' => ['vid' => $this->getVid()]],
          ],
        ],
      ],
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
        'name' => ['order' => 'desc'],
      ],
      'query' => [
        'bool' => [
          'must' => [
            ['term' => ['vid' => $this->getVid()]],
          ],
        ],
      ],
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
   * Test filter query.
   */
  public function testFilter(): void {
    $expected = [
      'sort' => [],
      'query' => [
        'bool' => [
          'must' => [
            ['term' => ['name' => 'value']],
            [
              'bool' => [
                'should' => [
                  ['term' => ['tid' => 1]],
                  ['term' => ['tid' => 2]],
                  ['term' => ['tid' => 3]],
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
    ];

    $container = [];
    $this->getSut($container, [$this->createElasticsearchResponse([])])
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('title', 'value')
      ->condition('tid', [1, 2, 3], 'IN')
      ->condition('title', 'test', 'CONTAINS')
      ->execute();

    $this->assertHttpHistoryContainer($expected, $container);
  }

}
