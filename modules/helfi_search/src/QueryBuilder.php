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
          'search_api_language',
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
   *
   * @return array<mixed>
   *   An array with 'index' and 'body' keys for Elasticsearch.
   */
  public function buildKnnQuery(array $embeddings, string $language, string $model, ?array $bundles = NULL, int $size = self::KNN_DEFAULT_SIZE, int $from = 0): array {
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

    $source = ['id', 'entity_type', 'entity_bundle', 'url', 'label', 'search_api_language', 'search_api_datasource'];

    $innerHits = [
      '_source' => FALSE,
      'fields' => [$fieldPrefix . '.content'],
      'size' => 1,
    ];

    $deboostBundles = $this->getSetting('deboost_bundles') ?? [];
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
      // their raw similarity is sufficiently higher. When $bundles is set
      // we whitelist its normal subset; otherwise we exclude $deboostBundles,
      // leaving the rest of the index searchable.
      $contentBool = $normalSubset !== NULL
        ? ['must' => [$languageFilter, ['terms' => ['entity_bundle' => $normalSubset]]]]
        : ['must' => [$languageFilter], 'must_not' => [['terms' => ['entity_bundle' => $deboostBundles]]]];

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
      $filter = $bundles
        ? ['bool' => ['must' => [$languageFilter, ['terms' => ['entity_bundle' => $bundles]]]]]
        : $languageFilter;
      $knn = $this->buildKnnEntry($fieldPrefix, $embeddings, $filter, $minScore, $innerHits, NULL);
    }

    return [
      'index' => self::EMBEDDINGS_INDEX,
      'body' => [
        'knn' => $knn,
        'size' => $size,
        'from' => $from,
        '_source' => $source,
      ],
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
      // Inner_hits is keyed by either the nested field path (default) or the
      // per-clause name we set when there are multiple KNN clauses. A hit
      // belongs to exactly one clause, so its inner_hits has a single entry.
      $innerGroup = array_first($hit['inner_hits'] ?? []) ?? [];
      $results[] = [
        'id' => $hit['_id'] ?? NULL,
        'score' => $hit['_score'] ?? 0,
        'entity_type' => array_first($hit['_source']['entity_type'] ?? []),
        'bundle' => array_first($hit['_source']['entity_bundle'] ?? []),
        'datasource' => array_first($hit['_source']['search_api_datasource'] ?? []),
        'url' => array_first($hit['_source']['url'] ?? []),
        'title' => array_first($hit['_source']['label'] ?? []),
        'language' => array_first($hit['_source']['search_api_language'] ?? []),
        'content' => $innerGroup['hits']['hits'][0]['fields'][$fieldPrefix][0]['content'][0] ?? '',
      ];
    }
    return $results;
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
        'language' => array_first($hit['_source']['search_api_language'] ?? []),
        'score' => $hit['_score'] ?? 0,
      ];
    }
    return $promotions;
  }

}
