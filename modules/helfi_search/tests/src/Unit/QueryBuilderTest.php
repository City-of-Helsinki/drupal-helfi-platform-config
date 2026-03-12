<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_search\Unit;

use Drupal\helfi_search\QueryBuilder;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the QueryBuilder service.
 */
#[Group('helfi_search')]
class QueryBuilderTest extends UnitTestCase {

  /**
   * Data provider for language field mapping.
   */
  public static function languageFieldProvider(): array {
    return [
      'finnish' => ['fi', 'keywords.fi'],
      'swedish' => ['sv', 'keywords.sv'],
      'english' => ['en', 'keywords.en'],
      'unknown language' => ['de', 'keywords.en'],
    ];
  }

  /**
   * Tests that buildPromotionQuery picks correct field per language.
   */
  #[DataProvider('languageFieldProvider')]
  public function testBuildPromotionQueryFieldMapping(string $language, string $expectedField): void {
    $query = (new QueryBuilder())->buildPromotionQuery('test', $language);

    $this->assertEquals(QueryBuilder::PROMOTIONS_INDEX, $query['index']);
    $this->assertArrayHasKey($expectedField, $query['body']['query']['bool']['must']['match']);
    $this->assertEquals('test', $query['body']['query']['bool']['must']['match'][$expectedField]['query']);
    $this->assertEquals('AUTO', $query['body']['query']['bool']['must']['match'][$expectedField]['fuzziness']);
    $this->assertEquals($language, $query['body']['query']['bool']['filter']['term']['search_api_language']);
    $this->assertEquals(QueryBuilder::PROMOTIONS_LIMIT, $query['body']['size']);
    $this->assertEquals(['title', 'description', 'link', 'search_api_language'], $query['body']['_source']);
  }

  /**
   * Tests parsePromotionHits extracts data correctly.
   */
  public function testParsePromotionHits(): void {
    $response = [
      'hits' => [
        'hits' => [
          [
            '_score' => 0.9,
            '_source' => [
              'title' => ['Test Promotion'],
              'description' => ['A description'],
              'link' => ['https://example.com/page'],
              'search_api_language' => ['fi'],
            ],
          ],
          [
            '_score' => 0.7,
            '_source' => [
              'title' => ['Another Promotion'],
              'description' => ['Another description'],
              'link' => ['/sv/another'],
              'search_api_language' => ['sv'],
            ],
          ],
        ],
      ],
    ];

    $results = (new QueryBuilder())->parsePromotionHits($response);

    $this->assertCount(2, $results);

    $this->assertEquals('Test Promotion', $results[0]['title']);
    $this->assertEquals('A description', $results[0]['description']);
    $this->assertEquals('https://example.com/page', $results[0]['url']);
    $this->assertEquals('fi', $results[0]['language']);
    $this->assertEquals(0.9, $results[0]['score']);

    $this->assertEquals('Another Promotion', $results[1]['title']);
    $this->assertEquals('sv', $results[1]['language']);
  }

  /**
   * Tests parsePromotionHits with empty response.
   */
  public function testParsePromotionHitsEmpty(): void {
    $queryBuilder = new QueryBuilder();
    $this->assertEmpty($queryBuilder->parsePromotionHits([]));
    $this->assertEmpty($queryBuilder->parsePromotionHits(['hits' => ['hits' => []]]));
  }

  /**
   * Tests buildKnnQuery without inner hits.
   */
  public function testBuildKnnQuery(): void {
    $vector = [0.1, 0.2, 0.3];
    $query = (new QueryBuilder())->buildKnnQuery($vector, 'fi');

    $this->assertEquals(QueryBuilder::EMBEDDINGS_INDEX, $query['index']);
    $this->assertEquals('embeddings.vector', $query['body']['knn']['field']);
    $this->assertEquals($vector, $query['body']['knn']['query_vector']);
    $this->assertEquals(10, $query['body']['knn']['k']);
    $this->assertEquals('fi', $query['body']['knn']['filter']['term']['search_api_language']);
    $this->assertArrayNotHasKey('inner_hits', $query['body']['knn']);
    $this->assertEquals(['entity_type', 'url', 'label', 'search_api_language'], $query['body']['_source']);
  }

  /**
   * Tests buildKnnQuery with inner hits.
   */
  public function testBuildKnnQueryWithInnerHits(): void {
    $query = (new QueryBuilder())->buildKnnQuery([0.1], 'sv', includeInnerHits: TRUE);

    $this->assertArrayHasKey('inner_hits', $query['body']['knn']);
    $this->assertEquals(['embeddings.content'], $query['body']['knn']['inner_hits']['fields']);
    $this->assertContains('id', $query['body']['_source']);
    $this->assertContains('search_api_datasource', $query['body']['_source']);
  }

  /**
   * Tests parseKnnHits without content.
   */
  public function testParseKnnHits(): void {
    $response = [
      'hits' => [
        'hits' => [
          [
            '_id' => 'doc1',
            '_score' => 0.95,
            '_source' => [
              'entity_type' => ['node'],
              'url' => ['/fi/test'],
              'label' => ['Test Page'],
              'search_api_language' => ['fi'],
            ],
          ],
        ],
      ],
    ];

    $results = (new QueryBuilder())->parseKnnHits($response);

    $this->assertCount(1, $results);
    $this->assertEquals(0.95, $results[0]['score']);
    $this->assertEquals('node', $results[0]['entity_type']);
    $this->assertEquals('/fi/test', $results[0]['url']);
    $this->assertEquals('Test Page', $results[0]['title']);
    $this->assertEquals('fi', $results[0]['language']);
    $this->assertArrayNotHasKey('content', $results[0]);
  }

  /**
   * Tests parseKnnHits with content extraction.
   */
  public function testParseKnnHitsWithContent(): void {
    $response = [
      'hits' => [
        'hits' => [
          [
            '_id' => 'doc1',
            '_score' => 0.9,
            '_source' => [
              'entity_type' => ['node'],
              'url' => ['/fi/page'],
              'label' => ['Page'],
              'search_api_language' => ['fi'],
              'search_api_datasource' => ['entity:node'],
            ],
            'inner_hits' => [
              'embeddings' => [
                'hits' => [
                  'hits' => [
                    [
                      'fields' => [
                        'embeddings' => [
                          ['content' => ['Some content']],
                        ],
                      ],
                    ],
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
    ];

    $results = (new QueryBuilder())->parseKnnHits($response, includeContent: TRUE);

    $this->assertCount(1, $results);
    $this->assertEquals('doc1', $results[0]['id']);
    $this->assertEquals('entity:node', $results[0]['datasource']);
    $this->assertEquals('Some content', $results[0]['content']);
  }

  /**
   * Tests parseKnnHits with empty response.
   */
  public function testParseKnnHitsEmpty(): void {
    $this->assertEmpty((new QueryBuilder())->parseKnnHits([]));
  }

}
