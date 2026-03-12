<?php

declare(strict_types=1);

namespace Drupal\helfi_search;

use Drupal\helfi_search\OpenAI\EmbeddingsApi;

/**
 * Builds Elasticsearch queries for search features.
 */
final class QueryBuilder {

  const string EMBEDDINGS_INDEX = 'embeddings';
  const string PROMOTIONS_INDEX = 'search_promotions';
  const int PROMOTIONS_LIMIT = 3;

  /**
   * Build a promotion search query for use in search() or msearch().
   *
   * @param string $query
   *   The search query string.
   * @param string $language
   *   The language code.
   *
   * @return array
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
              'match' => [
                $field => [
                  'query' => $query,
                  'fuzziness' => 'AUTO',
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
   * @param array $embeddings
   *   The embedding vector.
   * @param string $language
   *   The language code.
   * @param string $model
   *   The embedding model name.
   * @param bool $includeInnerHits
   *   Whether to include inner_hits for content extraction.
   *
   * @return array
   *   An array with 'index' and 'body' keys for Elasticsearch.
   */
  public function buildKnnQuery(array $embeddings, string $language, string $model, bool $includeInnerHits = FALSE): array {
    $language = match($language) {
      "fi", "sv", "en" => $language,
      default => "en",
    };

    $fieldPrefix = 'embeddings_' . EmbeddingsApi::sanitizeModelName($model);

    $knn = [
      'field' => $fieldPrefix . '.vector',
      'query_vector' => $embeddings,
      'k' => 10,
      'num_candidates' => 100,
      'filter' => [
        'term' => [
          'search_api_language' => $language,
        ],
      ],
    ];

    if ($includeInnerHits) {
      $knn['inner_hits'] = [
        '_source' => FALSE,
        'fields' => [$fieldPrefix . '.content'],
        'size' => 1,
      ];
    }

    $source = ['entity_type', 'url', 'label', 'search_api_language'];
    if ($includeInnerHits) {
      $source = ['id', ...$source, 'search_api_datasource'];
    }

    return [
      'index' => self::EMBEDDINGS_INDEX,
      'body' => [
        'knn' => $knn,
        'size' => 10,
        '_source' => $source,
      ],
    ];
  }

  /**
   * Parse KNN hits from an Elasticsearch response.
   *
   * @param array $response
   *   The Elasticsearch response array.
   * @param string $model
   *   The embedding model name.
   * @param bool $includeContent
   *   Whether to extract content from inner_hits.
   *
   * @return array
   *   Parsed search results.
   */
  public function parseKnnHits(array $response, string $model, bool $includeContent = FALSE): array {
    $fieldPrefix = 'embeddings_' . EmbeddingsApi::sanitizeModelName($model);
    $results = [];

    foreach ($response['hits']['hits'] ?? [] as $hit) {
      $result = [
        'score' => $hit['_score'] ?? 0,
        'entity_type' => array_first($hit['_source']['entity_type'] ?? []),
        'url' => array_first($hit['_source']['url'] ?? []),
        'title' => array_first($hit['_source']['label'] ?? []),
        'language' => array_first($hit['_source']['search_api_language'] ?? []),
      ];

      if ($includeContent) {
        $result['id'] = $hit['_id'];
        $result['datasource'] = array_first($hit['_source']['search_api_datasource'] ?? []);
        $result['content'] = $hit['inner_hits'][$fieldPrefix]['hits']['hits'][0]['fields'][$fieldPrefix][0]['content'][0] ?? '';
      }

      $results[] = $result;
    }
    return $results;
  }

  /**
   * Parse promotion hits from an Elasticsearch response.
   *
   * @param array $response
   *   The Elasticsearch response array.
   *
   * @return array
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
