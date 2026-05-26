<?php

declare(strict_types=1);

namespace Drupal\helfi_search;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\helfi_search\OpenAI\EmbeddingsApi;

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
   * @param string $query
   *   The search query string.
   * @param string $language
   *   The language code.
   *
   * @return array<mixed>
   *   An array with 'index' and 'body' keys for Elasticsearch.
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
              'match_phrase' => [
                $field => [
                  'query' => $query,
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
   * @param string $model
   *   The embedding model name.
   * @param string[]|null $bundles
   *   Filter only given bundles.
   * @param int $size
   *   Number of results per page.
   * @param int $from
   *   Offset for pagination.
   * @param string[]|null $excludeBundles
   *   Bundles that must not appear in results.
   * @param bool $includeAggregations
   *   When TRUE, attach a per-bundle terms aggregation to the body. Intended
   *   for debug responses; the caller is responsible for gating this.
   *
   * @return array<mixed>
   *   An array with 'index' and 'body' keys for Elasticsearch.
   */
  public function buildKnnQuery(array $embeddings, string $language, string $model, ?array $bundles = NULL, int $size = self::KNN_DEFAULT_SIZE, int $from = 0, ?array $excludeBundles = NULL, bool $includeAggregations = FALSE): array {
    $minScore = (float) ($this->getSetting('min_score') ?? 0.0);
    $language = match($language) {
      "fi", "sv", "en" => $language,
      default => "en",
    };

    $fieldPrefix = 'embeddings_' . EmbeddingsApi::sanitizeModelName($model);

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
      'size' => 1,
    ];

    $deboostBundles = $this->getSetting('deboost_bundles') ?? [];
    // Excluded bundles are off-limits — drop them from the deboost universe
    // so they can never appear, even via the deboosted clause.
    if ($excludeBundles) {
      $deboostBundles = array_values(array_diff($deboostBundles, $excludeBundles));
    }
    // Partition into deboosted vs. normal bundles. NULL on the non-deboosted
    // side means "everything not in $deboostBundles". used when no $bundles
    // filter is set.
    $deboostedSubset = $bundles
      ? array_values(array_intersect($bundles, $deboostBundles))
      : $deboostBundles;
    $normalSubset = $bundles
      ? array_values(array_diff($bundles, $deboostBundles))
      : NULL;

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

      // Each KNN clause needs a unique inner_hits name. Otherwise both
      // clauses keep the default key (the nested field path) and ES rejects
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
      if ($bundles || $excludeBundles) {
        $bool = ['must' => [$languageFilter]];
        if ($bundles) {
          $bool['must'][] = ['terms' => ['entity_bundle' => $bundles]];
        }
        if ($excludeBundles) {
          $bool['must_not'] = [['terms' => ['entity_bundle' => $excludeBundles]]];
        }
        $filter = ['bool' => $bool];
      }
      else {
        $filter = $languageFilter;
      }
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
   * @param string $model
   *   The embedding model name.
   *
   * @return array<mixed>
   *   Parsed search results.
   */
  public function parseKnnHits(array $response, string $model): array {
    $fieldPrefix = 'embeddings_' . EmbeddingsApi::sanitizeModelName($model);
    $results = [];

    foreach ($response['hits']['hits'] ?? [] as $hit) {
      // With multiple KNN clauses (deboost mode), each hit echoes every named
      // inner_hits clause, including empty ones for clauses that didn't
      // match. Pick the first group that actually has hits.
      $innerGroup = array_find(
        $hit['inner_hits'] ?? [],
        static fn (array $group): bool => !empty($group['hits']['hits']),
      ) ?? [];
      $innerFields = $innerGroup['hits']['hits'][0]['fields'][$fieldPrefix][0] ?? [];
      $results[] = [
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
