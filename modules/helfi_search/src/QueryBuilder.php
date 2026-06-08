<?php

declare(strict_types=1);

namespace Drupal\helfi_search;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Builds Elasticsearch queries for search features.
 */
final class QueryBuilder {

  const string EMBEDDINGS_INDEX = 'embeddings';
  const string PROMOTIONS_INDEX = 'search_promotions';
  const int PROMOTIONS_LIMIT = 3;
  const int KNN_DEFAULT_SIZE = 10;
  const int KNN_MAX_SIZE = 50;

  public function __construct(
    private readonly ?ConfigFactoryInterface $configFactory = NULL,
  ) {
  }

  /**
   * Build a promotion search query for use in search() or msearch().
   *
   * This method is tested in etusivu.
   *
   * @param string $query
   *   The search query string.
   * @param string $language
   *   The language code.
   *
   * @return array<mixed>
   *   An array with 'index' and 'body' keys for Elasticsearch.
   *
   * @see \Drupal\Tests\helfi_etusivu\Kernel\Search\PromotionQueryTest
   */
  public function buildPromotionQuery(string $query, string $language): array {
    $field = match ($language) {
      "fi", "sv", "en" => "keywords.$language",
      default => "keywords.en",
    };

    return [
      'index' => self::PROMOTIONS_INDEX,
      'body' => [
        'query' => [
          'bool' => [
            'must' => [
              'match' => [
                $field => [
                  'query' => $query,
                  'operator' => 'or',
                ],
              ],
            ],
            'filter' => [
              'term' => [
                'search_api_language' => $language,
              ],
            ],
          ],
        ],
        'size' => self::PROMOTIONS_LIMIT,
        '_source' => [
          'title',
          'description',
          'link',
        ],
      ],
    ];
  }

  /**
   * Build a KNN search query for use in search() or msearch().
   *
   * @param float[] $embeddings
   *   The embedding vector.
   * @param string $language
   *   The language code.
   * @param \Drupal\helfi_search\EmbeddingModel $model
   *   The embedding model to use.
   * @param string[]|null $bundles
   *   Filter only given bundles.
   * @param int $size
   *   Number of results per page.
   * @param int $from
   *   Offset for pagination.
   * @param string[]|null $excludeBundles
   *   Bundles that must not appear in results.
   * @param int $innerHitsSize
   *   How many matching chunks to return per parent doc. Defaults to 1
   *   (only the top chunk is surfaced). Set higher to surface per-chunk
   *   scores for debugging.
   * @param bool $includeAggregations
   *   When TRUE, attach a per-bundle terms aggregation to the body. Intended
   *   for debug responses; the caller is responsible for gating this.
   *
   * @return array<mixed>
   *   An array with 'index' and 'body' keys for Elasticsearch.
   */
  public function buildKnnQuery(
    array $embeddings,
    string $language,
    EmbeddingModel $model,
    ?array $bundles = NULL,
    int $size = self::KNN_DEFAULT_SIZE,
    int $from = 0,
    ?array $excludeBundles = NULL,
    int $innerHitsSize = 1,
    bool $includeAggregations = FALSE,
  ): array {
    $minScore = (float) ($this->getSetting('min_score') ?? 0.0);
    $language = match($language) {
      "fi", "sv", "en" => $language,
      default => "en",
    };

    $fieldPrefix = $model->fieldPrefix();

    $languageFilter = [
      'term' => [
        'search_api_language' => $language,
      ],
    ];

    $source = ['id', 'entity_type', 'entity_bundle', 'url', 'label', 'published_at'];

    $innerHits = [
      '_source' => FALSE,
      'fields' => [
        $fieldPrefix . '.content',
        $fieldPrefix . '.fragment',
      ],
      'size' => $innerHitsSize,
    ];

    [$deboostBundles, $deboostedSubset, $normalSubset] = $this->partitionForDeboost($bundles, $excludeBundles);
    $applyDeboost = !empty($deboostedSubset) && ($normalSubset === NULL || !empty($normalSubset));

    if ($applyDeboost) {
      // Two parallel KNN clauses with disjoint bundle filters and different
      // boosts. Documents in $deboostedSubset get their score multiplied by
      // $deboostFactor, so they only out-rank non-deboosted documents when
      // their raw similarity is sufficiently higher.
      $contentMustNot = $excludeBundles
        ? array_values(array_unique(array_merge($deboostBundles, $excludeBundles)))
        : $deboostBundles;
      $contentBool = $normalSubset !== NULL
        ? ['must' => [$languageFilter, ['terms' => ['entity_bundle' => $normalSubset]]]]
        : ['must' => [$languageFilter], 'must_not' => [['terms' => ['entity_bundle' => $contentMustNot]]]];

      // Each KNN clause needs a unique inner_hits name. Otherwise, both
      // clauses keep the default key (the nested field path), and ES rejects
      // the search.
      $knn = [
        $this->buildKnnEntry(
          $fieldPrefix,
          $embeddings,
          ['bool' => ['must' => [$languageFilter, ['terms' => ['entity_bundle' => $deboostedSubset]]]]],
          $minScore,
          ['name' => 'deboosted'] + $innerHits,
          (float) ($this->getSetting('deboost_factor') ?? 1.0),
        ),
        $this->buildKnnEntry(
          $fieldPrefix,
          $embeddings,
          ['bool' => $contentBool],
          $minScore,
          ['name' => 'content'] + $innerHits,
          1.0,
        ),
      ];
    }
    else {
      $filter = $this->buildBundleFilter($languageFilter, $bundles, $excludeBundles);
      $knn = $this->buildKnnEntry($fieldPrefix, $embeddings, $filter, $minScore, $innerHits, NULL);
    }

    $body = [
      'knn' => $knn,
      'size' => $size,
      'from' => $from,
      '_source' => $source,
    ];

    if ($includeAggregations) {
      // Aggregations operate on the documents matched by the KNN clauses
      // (capped at `k` per clause), so the counts reflect what KNN actually
      // surfaced, not the entire index.
      $body['aggs'] = [
        'bundles' => [
          'terms' => [
            'field' => 'entity_bundle',
            'size' => 50,
          ],
        ],
      ];
    }

    return [
      'index' => self::EMBEDDINGS_INDEX,
      'body' => $body,
    ];
  }

  /**
   * Partition the bundle filter into deboosted vs. normal subsets.
   *
   * NULL on the non-deboosted side means "everything not in $deboostBundles",
   * used when no $bundles filter is set. Excluded bundles are removed from
   * the deboost universe so they can never appear, even via the deboost
   * clause.
   *
   * @param string[]|null $bundles
   *   Caller-selected bundle filter, or NULL.
   * @param string[]|null $excludeBundles
   *   Bundles that must not appear in results, or NULL.
   *
   * @return array{0: string[], 1: string[], 2: string[]|null}
   *   [$deboostBundles, $deboostedSubset, $normalSubset].
   */
  private function partitionForDeboost(?array $bundles, ?array $excludeBundles): array {
    $deboostBundles = $this->getSetting('deboost_bundles') ?? [];
    if ($excludeBundles) {
      $deboostBundles = array_values(array_diff($deboostBundles, $excludeBundles));
    }
    $deboostedSubset = $bundles
      ? array_values(array_intersect($bundles, $deboostBundles))
      : $deboostBundles;
    $normalSubset = $bundles
      ? array_values(array_diff($bundles, $deboostBundles))
      : NULL;
    return [$deboostBundles, $deboostedSubset, $normalSubset];
  }

  /**
   * Build the filter clause for the single-KNN (non-deboost) path.
   *
   * @param array<string, mixed> $languageFilter
   *   The base language term clause.
   * @param string[]|null $bundles
   *   Bundles to include, or NULL.
   * @param string[]|null $excludeBundles
   *   Bundles to exclude, or NULL.
   *
   * @return array<string, mixed>
   *   An Elasticsearch filter clause.
   */
  private function buildBundleFilter(array $languageFilter, ?array $bundles, ?array $excludeBundles): array {
    if (!$bundles && !$excludeBundles) {
      return $languageFilter;
    }
    $bool = ['must' => [$languageFilter]];
    if ($bundles) {
      $bool['must'][] = ['terms' => ['entity_bundle' => $bundles]];
    }
    if ($excludeBundles) {
      $bool['must_not'] = [['terms' => ['entity_bundle' => $excludeBundles]]];
    }
    return ['bool' => $bool];
  }

  /**
   * Read a single value from helfi_search.settings.
   *
   * @return mixed
   *   The configured value, or NULL when the config factory is absent or the
   *   key is unset.
   */
  private function getSetting(string $key): mixed {
    return $this->configFactory?->get('helfi_search.settings')->get($key);
  }

  /**
   * Build a single KNN clause body.
   *
   * @param string $fieldPrefix
   *   Embeddings field prefix (e.g. 'embeddings_text_embedding_3_large').
   * @param float[] $embeddings
   *   The query vector.
   * @param array<string, mixed> $filter
   *   Elasticsearch filter clause to apply to this KNN search.
   * @param float|null $similarity
   *   Optional raw-similarity floor applied before boost.
   * @param array<string, mixed> $innerHits
   *   Inner_hits sub-clause for content extraction.
   * @param float|null $boost
   *   Optional score multiplier for this KNN clause.
   *
   * @return array<string, mixed>
   *   The KNN clause body.
   */
  private function buildKnnEntry(string $fieldPrefix, array $embeddings, array $filter, ?float $similarity, array $innerHits, ?float $boost): array {
    $entry = [
      'field' => $fieldPrefix . '.vector',
      'query_vector' => $embeddings,
      'k' => 50,
      'num_candidates' => 500,
      'filter' => $filter,
      'inner_hits' => $innerHits,
    ];
    // https://www.elastic.co/docs/solutions/search/vector/knn#knn-similarity-search.
    // The _score of each document will be derived from the similarity,
    // in a way that ensures that a larger score corresponds to a higher
    // ranking. We use cosine similarity metric.
    if ($similarity !== NULL) {
      // min_score = (2 * _score) - 1.
      $entry['similarity'] = $similarity;
    }
    // https://www.elastic.co/docs/solutions/search/vector/knn#_search_multiple_knn_fields.
    if ($boost !== NULL) {
      $entry['boost'] = $boost;
    }
    return $entry;
  }

  /**
   * Parse KNN hits from an Elasticsearch response.
   *
   * @param array<mixed> $response
   *   The Elasticsearch response array.
   * @param \Drupal\helfi_search\EmbeddingModel $model
   *   The embedding model to use.
   *
   * @return array<mixed>
   *   Parsed search results.
   */
  public function parseKnnHits(array $response, EmbeddingModel $model): array {
    $fieldPrefix = $model->fieldPrefix();
    $results = [];

    foreach ($response['hits']['hits'] ?? [] as $hit) {
      // With multiple KNN clauses (deboost mode), each hit echoes every named
      // inner_hits clause, including empty ones for clauses that didn't
      // match. Pick the first group that actually has hits.
      $innerGroup = array_find(
        $hit['inner_hits'] ?? [],
        static fn (array $group): bool => !empty($group['hits']['hits']),
      ) ?? [];
      $innerHits = $innerGroup['hits']['hits'] ?? [];
      $innerFields = $innerHits[0]['fields'][$fieldPrefix][0] ?? [];
      $result = [
        'id' => $hit['_id'] ?? NULL,
        'score' => $hit['_score'] ?? 0,
        'entity_type' => array_first($hit['_source']['entity_type'] ?? []),
        'bundle' => array_first($hit['_source']['entity_bundle'] ?? []),
        'url' => array_first($hit['_source']['url'] ?? []),
        'title' => array_first($hit['_source']['label'] ?? []),
        'published_at' => array_first($hit['_source']['published_at'] ?? []),
        'content' => $innerFields['content'][0] ?? '',
        'fragment' => $innerFields['fragment'][0] ?? NULL,
      ];
      // Debug: when more than one inner hit was requested, surface every
      // matching chunk with its individual similarity score.
      if (count($innerHits) > 1) {
        $result['chunks'] = array_map(
          static fn (array $h) => [
            'score' => $h['_score'] ?? 0,
            'content' => $h['fields'][$fieldPrefix][0]['content'][0] ?? '',
            'fragment' => $h['fields'][$fieldPrefix][0]['fragment'][0] ?? NULL,
          ],
          $innerHits,
        );
      }
      $results[] = $result;
    }
    return $results;
  }

  /**
   * Parse bundle aggregation buckets from an Elasticsearch response.
   *
   * @param array<mixed> $response
   *   The Elasticsearch response array.
   *
   * @return array<string, int>
   *   Map of entity bundle => doc count, in bucket order returned by ES.
   */
  public function parseBundleAggregations(array $response): array {
    $buckets = $response['aggregations']['bundles']['buckets'] ?? [];
    $result = [];
    foreach ($buckets as $bucket) {
      $key = $bucket['key'] ?? NULL;
      if (is_string($key) && $key !== '') {
        $result[$key] = (int) ($bucket['doc_count'] ?? 0);
      }
    }
    return $result;
  }

  /**
   * Parse promotion hits from an Elasticsearch response.
   *
   * @param array<mixed> $response
   *   The Elasticsearch response array.
   *
   * @return array<mixed>
   *   Parsed promotion results.
   */
  public function parsePromotionHits(array $response): array {
    $promotions = [];
    foreach ($response['hits']['hits'] ?? [] as $hit) {
      $promotions[] = [
        'title' => array_first($hit['_source']['title'] ?? []),
        'description' => array_first($hit['_source']['description'] ?? []),
        'url' => array_first($hit['_source']['link'] ?? []),
        'score' => $hit['_score'] ?? 0,
      ];
    }
    return $promotions;
  }

}
