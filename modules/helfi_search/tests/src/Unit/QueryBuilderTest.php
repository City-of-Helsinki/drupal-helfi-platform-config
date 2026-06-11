<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_search\Unit;

use Drupal\helfi_search\EmbeddingModel;
use Drupal\helfi_search\QueryBuilder;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the QueryBuilder service.
 */
#[Group('helfi_search')]
class QueryBuilderTest extends UnitTestCase {

  private const EmbeddingModel TEST_MODEL = EmbeddingModel::Small;
  private const string TEST_MODEL_FIELD = 'embeddings_text_embedding_3_small';

  private const float TEST_MIN_SCORE = 0.68;

  /**
   * Build a QueryBuilder with stubbed config.
   *
   * Defaults to deboost disabled so callers test the non-deboost branch
   * unless they explicitly opt in.
   *
   * @param list<string> $deboostBundles
   *   Bundles to apply the deboost factor to. Empty disables deboost.
   * @param float $deboostFactor
   *   Score multiplier for deboosted bundles.
   * @param float $minScore
   *   Minimum similarity floor.
   */
  private function createBuilder(array $deboostBundles = [], float $deboostFactor = 0.5, float $minScore = self::TEST_MIN_SCORE): QueryBuilder {
    return new QueryBuilder($this->getConfigFactoryStub([
      'helfi_search.settings' => [
        'deboost_bundles' => $deboostBundles,
        'deboost_factor' => $deboostFactor,
        'min_score' => $minScore,
      ],
    ]));
  }

  /**
   * Data provider for language field mapping.
   *
   * @phpstan-return array<string, array{string, string}>
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
   * Tests buildPromotionQuery percolates the user query, filtered by language.
   */
  public function testBuildPromotionQuery(): void {
    $query = (new QueryBuilder())->buildPromotionQuery('test', 'fi');

    $this->assertEquals(QueryBuilder::PROMOTIONS_INDEX, $query['index']);

    $percolate = $query['body']['query']['bool']['must']['percolate'];
    $this->assertEquals('query', $percolate['field']);
    $this->assertEquals(['keywords' => 'test'], $percolate['document']);

    $this->assertEquals('fi', $query['body']['query']['bool']['filter']['term']['search_api_language']);
    $this->assertEquals(QueryBuilder::PROMOTIONS_LIMIT, $query['body']['size']);
    $this->assertEquals(['title', 'description', 'link'], $query['body']['_source']);
  }

  /**
   * Tests the stored percolator query picks the correct field per language.
   */
  #[DataProvider('languageFieldProvider')]
  public function testBuildPromotionPercolatorQueryFieldMapping(string $language, string $expectedField): void {
    $query = QueryBuilder::buildPromotionPercolatorQuery(['sauna', 'uimahalli'], $language);

    $should = $query['bool']['should'];
    $this->assertEquals(1, $query['bool']['minimum_should_match']);
    // One match clause per keyword, each requiring all of its tokens.
    $this->assertCount(2, $should);
    $this->assertArrayHasKey($expectedField, $should[0]['match']);
    $this->assertEquals('sauna', $should[0]['match'][$expectedField]['query']);
    $this->assertEquals('and', $should[0]['match'][$expectedField]['operator']);
    $this->assertEquals('uimahalli', $should[1]['match'][$expectedField]['query']);
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
    $this->assertEquals(0.9, $results[0]['score']);

    $this->assertEquals('Another Promotion', $results[1]['title']);
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
   * Tests buildKnnQuery basic structure including always-on inner_hits.
   */
  public function testBuildKnnQuery(): void {
    $vector = [0.1, 0.2, 0.3];
    $query = $this->createBuilder()->buildKnnQuery($vector, 'fi', self::TEST_MODEL);

    $this->assertEquals(QueryBuilder::EMBEDDINGS_INDEX, $query['index']);
    $this->assertEquals(self::TEST_MODEL_FIELD . '.vector', $query['body']['knn']['field']);
    $this->assertEquals($vector, $query['body']['knn']['query_vector']);
    $this->assertEquals(50, $query['body']['knn']['k']);
    $this->assertEquals('fi', $query['body']['knn']['filter']['term']['search_api_language']);
    $this->assertEquals(
      [self::TEST_MODEL_FIELD . '.content', self::TEST_MODEL_FIELD . '.fragment'],
      $query['body']['knn']['inner_hits']['fields'],
    );
    $this->assertEquals(
      ['id', 'entity_type', 'entity_bundle', 'url', 'label', 'published_at', 'metatag_title'],
      $query['body']['_source'],
    );
    $this->assertEquals(QueryBuilder::KNN_DEFAULT_SIZE, $query['body']['size']);
    $this->assertEquals(0, $query['body']['from']);
    $this->assertEquals(self::TEST_MIN_SCORE, $query['body']['knn']['similarity']);
    $this->assertArrayNotHasKey('min_score', $query['body']);
  }

  /**
   * Tests buildKnnQuery with bundle filter.
   */
  public function testBuildKnnQueryWithBundleFilter(): void {
    $vector = [0.1, 0.2, 0.3];
    $bundles = ['news_article', 'page'];
    $query = $this->createBuilder()->buildKnnQuery($vector, 'fi', self::TEST_MODEL, bundles: $bundles);

    $filter = $query['body']['knn']['filter'];
    $this->assertArrayHasKey('bool', $filter);
    $this->assertCount(2, $filter['bool']['must']);
    $this->assertEquals('fi', $filter['bool']['must'][0]['term']['search_api_language']);
    $this->assertEquals($bundles, $filter['bool']['must'][1]['terms']['entity_bundle']);
  }

  /**
   * Tests parseKnnHits returns scalar fields when no inner_hits content.
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
              'entity_bundle' => ['news_article'],
              'url' => ['/fi/test'],
              'label' => ['Test Page'],
              'metatag_title' => ['Custom Page Title'],
              'published_at' => ['2026-05-04T12:00:00+00:00'],
            ],
          ],
        ],
      ],
    ];

    $results = (new QueryBuilder())->parseKnnHits($response, self::TEST_MODEL);

    $this->assertCount(1, $results);
    $this->assertEquals('doc1', $results[0]['id']);
    $this->assertEquals(0.95, $results[0]['score']);
    $this->assertEquals('node', $results[0]['entity_type']);
    $this->assertEquals('news_article', $results[0]['bundle']);
    $this->assertEquals('/fi/test', $results[0]['url']);
    $this->assertEquals('Test Page', $results[0]['title']);
    $this->assertEquals('Custom Page Title', $results[0]['metatag_title']);
    $this->assertEquals('2026-05-04T12:00:00+00:00', $results[0]['published_at']);
    // Missing inner_hits content gracefully degrades to empty string.
    $this->assertEquals('', $results[0]['content']);
    // Missing fragment gracefully degrades to NULL.
    $this->assertNull($results[0]['fragment']);
  }

  /**
   * Tests parseKnnHits extracts content from inner_hits.
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
            ],
            'inner_hits' => [
              self::TEST_MODEL_FIELD => [
                'hits' => [
                  'hits' => [
                    [
                      'fields' => [
                        self::TEST_MODEL_FIELD => [
                          [
                            'content' => ['Some content'],
                            'fragment' => ['some-section'],
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
      ],
    ];

    $results = (new QueryBuilder())->parseKnnHits($response, self::TEST_MODEL);

    $this->assertCount(1, $results);
    $this->assertEquals('doc1', $results[0]['id']);
    $this->assertEquals('Some content', $results[0]['content']);
  }

  /**
   * Tests parseKnnHits with empty response.
   */
  public function testParseKnnHitsEmpty(): void {
    $this->assertEmpty((new QueryBuilder())->parseKnnHits([], self::TEST_MODEL));
  }

  /**
   * Tests parseKnnHits skips empty inner_hits clauses.
   *
   * With deboost enabled, ES echoes every named inner_hits key on each hit,
   * including empty ones for clauses the document didn't match.
   */
  public function testParseKnnHitsWithNamedInnerHits(): void {
    $response = [
      'hits' => [
        'hits' => [
          [
            '_id' => 'doc1',
            '_score' => 0.9,
            '_source' => ['url' => ['/fi/x'], 'label' => ['X']],
            'inner_hits' => [
              'deboosted' => [
                'hits' => ['total' => ['value' => 0], 'hits' => []],
              ],
              'content' => [
                'hits' => [
                  'hits' => [
                    [
                      'fields' => [
                        self::TEST_MODEL_FIELD => [
                          [
                            'content' => ['Named hit content'],
                            'fragment' => ['fragment'],
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
      ],
    ];

    $results = (new QueryBuilder())->parseKnnHits($response, self::TEST_MODEL);

    $this->assertEquals('Named hit content', $results[0]['content']);
  }

  /**
   * Tests buildKnnQuery pagination parameters.
   */
  public function testBuildKnnQueryPagination(): void {
    $query = $this->createBuilder()->buildKnnQuery([0.1], 'fi', self::TEST_MODEL, size: 5, from: 10);

    $this->assertEquals(5, $query['body']['size']);
    $this->assertEquals(10, $query['body']['from']);
    // K is fixed to retrieve a large pool of candidates for pagination.
    $this->assertEquals(50, $query['body']['knn']['k']);
    $this->assertEquals(500, $query['body']['knn']['num_candidates']);
  }

  /**
   * Tests buildKnnQuery emits two KNN clauses when deboost is configured.
   */
  public function testBuildKnnQueryWithDeboost(): void {
    $vector = [0.1, 0.2, 0.3];
    $deboost = ['news_article', 'news_item'];
    $query = $this->createBuilder($deboost, 0.5)->buildKnnQuery($vector, 'fi', self::TEST_MODEL);

    $knn = $query['body']['knn'];
    $this->assertIsList($knn);
    $this->assertCount(2, $knn);

    [$news, $nonNews] = $knn;

    // News entry: filter requires the deboost bundles, boost is the factor.
    $this->assertEquals(0.5, $news['boost']);
    $this->assertEquals(self::TEST_MIN_SCORE, $news['similarity']);
    $this->assertEquals('fi', $news['filter']['bool']['must'][0]['term']['search_api_language']);
    $this->assertEquals($deboost, $news['filter']['bool']['must'][1]['terms']['entity_bundle']);

    // Non-news entry: filter excludes deboost bundles, boost is 1.0.
    $this->assertEquals(1.0, $nonNews['boost']);
    $this->assertEquals(self::TEST_MIN_SCORE, $nonNews['similarity']);
    $this->assertEquals('fi', $nonNews['filter']['bool']['must'][0]['term']['search_api_language']);
    $this->assertEquals($deboost, $nonNews['filter']['bool']['must_not'][0]['terms']['entity_bundle']);

    // Score floor lives in per-entry `similarity`, never top-level.
    $this->assertArrayNotHasKey('min_score', $query['body']);
  }

  /**
   * Tests deboost is inactive when caller picks only deboosted bundles.
   */
  public function testBuildKnnQueryDeboostSkippedWhenAllBundlesAreDeboosted(): void {
    $query = $this->createBuilder(['news_article', 'news_item'], 0.5)
      ->buildKnnQuery([0.1], 'fi', self::TEST_MODEL, bundles: ['news_article']);

    // No non-deboosted subset → single KNN with no boost.
    $this->assertArrayHasKey('field', $query['body']['knn']);
    $this->assertArrayNotHasKey('boost', $query['body']['knn']);
    $this->assertEquals(self::TEST_MIN_SCORE, $query['body']['knn']['similarity']);
  }

  /**
   * Tests deboost is inactive when caller picks no deboosted bundles.
   */
  public function testBuildKnnQueryDeboostSkippedWhenNoDeboostedSelected(): void {
    $query = $this->createBuilder(['news_article', 'news_item'], 0.5)
      ->buildKnnQuery([0.1], 'fi', self::TEST_MODEL, bundles: ['page', 'landing_page']);

    $this->assertArrayHasKey('field', $query['body']['knn']);
    $this->assertArrayNotHasKey('boost', $query['body']['knn']);
    $this->assertEquals(self::TEST_MIN_SCORE, $query['body']['knn']['similarity']);
  }

  /**
   * Tests deboost partitions when caller mixes deboosted and non-deboosted.
   */
  public function testBuildKnnQueryDeboostIntersectsBundleFilter(): void {
    $query = $this->createBuilder(['news_article', 'news_item'], 0.5)
      ->buildKnnQuery([0.1], 'fi', self::TEST_MODEL, bundles: ['news_article', 'page']);

    $knn = $query['body']['knn'];
    $this->assertIsList($knn);
    $this->assertCount(2, $knn);

    [$news, $content] = $knn;

    // News clause covers only the deboosted intersection.
    $this->assertEquals(0.5, $news['boost']);
    $this->assertEquals(['news_article'], $news['filter']['bool']['must'][1]['terms']['entity_bundle']);

    // Content clause whitelists the remaining caller-selected bundles.
    $this->assertEquals(1.0, $content['boost']);
    $this->assertEquals(['page'], $content['filter']['bool']['must'][1]['terms']['entity_bundle']);
    $this->assertArrayNotHasKey('must_not', $content['filter']['bool']);
  }

  /**
   * Tests deboost emits uniquely named inner_hits on both KNN entries.
   *
   * Without distinct names, ES rejects the search because both clauses would
   * reuse the nested field path as the inner_hits response key.
   */
  public function testBuildKnnQueryDeboostHasInnerHits(): void {
    $query = $this->createBuilder(['news_article', 'news_item'], 0.5)
      ->buildKnnQuery([0.1], 'fi', self::TEST_MODEL);

    [$news, $content] = $query['body']['knn'];

    $expectedFields = [self::TEST_MODEL_FIELD . '.content', self::TEST_MODEL_FIELD . '.fragment'];
    $this->assertEquals($expectedFields, $news['inner_hits']['fields']);
    $this->assertEquals($expectedFields, $content['inner_hits']['fields']);
    $this->assertEquals('deboosted', $news['inner_hits']['name']);
    $this->assertEquals('content', $content['inner_hits']['name']);
  }

  /**
   * Tests deboost is inactive when no config factory is wired up.
   */
  public function testBuildKnnQueryNoDeboostWithoutConfigFactory(): void {
    $query = (new QueryBuilder())->buildKnnQuery([0.1], 'fi', self::TEST_MODEL);

    // No config factory → empty deboost_bundles → single KNN entry.
    $this->assertArrayHasKey('field', $query['body']['knn']);
    $this->assertArrayNotHasKey('boost', $query['body']['knn']);
    // No min_score key in config → 0.0 floor (effectively disabled).
    $this->assertEquals(0.0, $query['body']['knn']['similarity']);
  }

  /**
   * Tests excludeBundles emits a must_not clause alongside the language term.
   */
  public function testBuildKnnQueryWithExcludeBundles(): void {
    $query = $this->createBuilder()
      ->buildKnnQuery([0.1], 'fi', self::TEST_MODEL, excludeBundles: ['news_item', 'news_article']);

    $filter = $query['body']['knn']['filter'];
    $this->assertEquals('fi', $filter['bool']['must'][0]['term']['search_api_language']);
    $this->assertEquals(['news_item', 'news_article'], $filter['bool']['must_not'][0]['terms']['entity_bundle']);
  }

  /**
   * Tests excludeBundles also forbids deboost entry from surfacing them.
   *
   * Excluded bundles must never appear in results, even via the deboost
   * clause. The deboost universe should shrink to drop them.
   */
  public function testBuildKnnQueryExcludeBundlesAlsoExcludedFromDeboost(): void {
    $query = $this->createBuilder(['news_article', 'news_item'], 0.5)
      ->buildKnnQuery([0.1], 'fi', self::TEST_MODEL, excludeBundles: ['news_item']);

    [$news, $content] = $query['body']['knn'];

    // Deboost clause keeps only the non-excluded deboost bundles.
    $this->assertEquals(['news_article'], $news['filter']['bool']['must'][1]['terms']['entity_bundle']);
    // Content clause must_not lists the original deboost set + the excludes.
    $this->assertEquals(
      ['news_article', 'news_item'],
      $content['filter']['bool']['must_not'][0]['terms']['entity_bundle'],
    );
  }

  /**
   * Tests debug-only options: innerHitsSize and includeAggregations.
   */
  public function testBuildKnnQueryDebugOptions(): void {
    $builder = $this->createBuilder();

    // Defaults: inner_hits.size === 1, no aggs block.
    $default = $builder->buildKnnQuery([0.1], 'fi', self::TEST_MODEL);
    $this->assertEquals(1, $default['body']['knn']['inner_hits']['size']);
    $this->assertArrayNotHasKey('aggs', $default['body']);

    // With both: inner_hits.size propagates, aggs block bucketed on bundle.
    $debug = $builder->buildKnnQuery(
      [0.1],
      'fi',
      self::TEST_MODEL,
      innerHitsSize: 25,
      includeAggregations: TRUE,
    );
    $this->assertEquals(25, $debug['body']['knn']['inner_hits']['size']);
    $this->assertEquals('entity_bundle', $debug['body']['aggs']['bundles']['terms']['field']);
  }

  /**
   * Tests parseBundleAggregations extracts bucket counts.
   */
  public function testParseBundleAggregations(): void {
    $builder = new QueryBuilder();

    $this->assertSame([], $builder->parseBundleAggregations([]));
    $this->assertSame(
      ['news_item' => 7, 'page' => 3],
      $builder->parseBundleAggregations([
        'aggregations' => [
          'bundles' => [
            'buckets' => [
              ['key' => 'news_item', 'doc_count' => 7],
              ['key' => 'page', 'doc_count' => 3],
              // Empty / non-string keys are dropped.
              ['key' => '', 'doc_count' => 1],
            ],
          ],
        ],
      ]),
    );
  }

}
