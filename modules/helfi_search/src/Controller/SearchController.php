<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Flood\FloodInterface;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_search\EmbeddingsModelException;
use Drupal\helfi_search\EmbeddingsModelInterface;
use Drupal\helfi_search\QueryBuilder;
use Drupal\helfi_search\QueryRewriter;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ElasticsearchException;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Elastic\Transport\Exception\TransportException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Semantic search API controller.
 */
final class SearchController extends ControllerBase {

  use AutowireTrait;

  const string FLOOD_EVENT = 'helfi_search.semantic_search';
  const int FLOOD_THRESHOLD = 100;
  const int FLOOD_WINDOW = 3600;

  const int MIN_QUERY_LENGTH = 3;
  const int MAX_QUERY_LENGTH = 500;

  // News bundles excluded when the React form submits the 'others' sentinel.
  const array NEWS_BUNDLES = ['news_item', 'news_article'];

  public function __construct(
    private readonly EmbeddingsModelInterface $embeddingsModel,
    private readonly FloodInterface $flood,
    private readonly QueryBuilder $queryBuilder,
    private readonly EnvironmentResolverInterface $environmentResolver,
    #[Autowire(service: 'helfi_platform_config.etusivu_elastic_client')]
    private readonly Client $elasticClient,
  ) {
  }

  /**
   * Whether debug data (e.g. per-bundle aggs) can be returned in responses.
   *
   * Debug data is exposed only outside production. If the environment cannot
   * be resolved we default to FALSE so production-like setups stay quiet.
   */
  private function isDebugAllowed(): bool {
    try {
      return $this->environmentResolver->getActiveEnvironmentName() !== EnvironmentEnum::Prod->value;
    }
    catch (\InvalidArgumentException) {
      return FALSE;
    }
  }

  /**
   * Get the model to use, from query parameter or first configured.
   */
  private function resolveModel(Request $request): ?string {
    $models = $this->config('helfi_search.settings')->get('openai_models') ?? [];

    if (empty($models)) {
      return NULL;
    }

    $requested = $request->query->getString('model');

    if ($requested && in_array($requested, $models, TRUE)) {
      return $requested;
    }

    return $models[0];
  }

  /**
   * Handle semantic search request.
   */
  public function search(Request $request): JsonResponse {
    $query = trim($request->query->getString('q'));

    if (mb_strlen($query) < self::MIN_QUERY_LENGTH || mb_strlen($query) > self::MAX_QUERY_LENGTH) {
      return new JsonResponse(
        ['error' => 'Query must be between ' . self::MIN_QUERY_LENGTH . ' and ' . self::MAX_QUERY_LENGTH . ' characters.'],
        400,
      );
    }

    $model = $this->resolveModel($request);

    if (!$model) {
      return new JsonResponse(
        ['error' => 'No embedding models configured.'],
        503,
      );
    }

    if (!$this->flood->isAllowed(self::FLOOD_EVENT, self::FLOOD_THRESHOLD, self::FLOOD_WINDOW)) {
      return new JsonResponse(
        ['error' => 'Too many requests. Please try again later.'],
        429,
      );
    }

    // Apply rewrite rules to query before embedding, so short
    // queries tokenize the same way they do in indexed content.
    $query = QueryRewriter::rewrite($query, $this->config('helfi_search.settings')->get('canonical_terms') ?? []);

    try {
      $embeddings = $this->embeddingsModel->getEmbedding($query, $model);

      // Register flood after the expensive embedding API call.
      $this->flood->register(self::FLOOD_EVENT, self::FLOOD_WINDOW);

      $currentLanguage = $this->languageManager()->getCurrentLanguage()->getId();
      [$bundles, $excludeBundles] = $this->resolveBundleFilters($request);

      $page = max(1, $request->query->getInt('page', 1));
      $size = min(QueryBuilder::KNN_MAX_SIZE, max(1, $request->query->getInt('size', QueryBuilder::KNN_DEFAULT_SIZE)));
      $debug = $request->query->getBoolean('debug') && $this->isDebugAllowed();

      $knnQuery = $this->queryBuilder->buildKnnQuery(
        $embeddings,
        $currentLanguage,
        $model,
        bundles: $bundles,
        size: $size,
        from: ($page - 1) * $size,
        excludeBundles: $excludeBundles,
        // Debug-only: ask ES to return every matching chunk so per-chunk
        // scores can be inspected.
        innerHitsSize: $debug ? QueryBuilder::KNN_MAX_SIZE : 1,
        includeAggregations: $debug,
      );

      $result = ($bundles || $excludeBundles)
        ? $this->executeFilteredSearch($knnQuery, $model, $debug)
        : $this->executeBlendedSearch($knnQuery, $query, $currentLanguage, $model, $debug);

      return $this->createResponse($result, $page, $size);
    }
    catch (EmbeddingsModelException | ElasticsearchException | TransportException) {
      return new JsonResponse(
        ['error' => 'Search service temporarily unavailable.'],
        503,
      );
    }
  }

  /**
   * Resolve the bundle inclusion/exclusion filters from the request.
   *
   * The React form uses two sentinels:
   * - 'news' expands to all configured news bundles.
   * - 'others' means "everything except news bundles".
   *
   * When both are selected (or 'others' is combined with an explicit news
   * bundle) the include and exclude cancel out, so all bundle filtering is
   * dropped.
   *
   * @return array{0: list<string>|null, 1: list<string>|null}
   *   [$bundles, $excludeBundles] — each side NULL when not constrained.
   */
  private function resolveBundleFilters(Request $request): array {
    $bundles = array_values(array_filter(
      array_map('trim', explode(',', $request->query->getString('bundle'))),
    ));

    // Expand the 'news' sentinel to the configured news bundles.
    if (in_array('news', $bundles, TRUE)) {
      $bundles = array_values(array_unique(array_merge(
        array_filter($bundles, static fn (string $b): bool => $b !== 'news'),
        self::NEWS_BUNDLES,
      )));
    }

    if (!in_array('others', $bundles, TRUE)) {
      return [$bundles ?: NULL, NULL];
    }

    $bundles = array_values(array_filter($bundles, static fn (string $b): bool => $b !== 'others'));
    if (array_intersect($bundles, self::NEWS_BUNDLES)) {
      return [NULL, NULL];
    }
    return [$bundles ?: NULL, self::NEWS_BUNDLES];
  }

  /**
   * Run the single-search() path used when a bundle filter is in effect.
   *
   * @param array<string, mixed> $knnQuery
   *   The pre-built KNN query (index + body).
   * @param string $model
   *   The embedding model name.
   * @param bool $debug
   *   Whether to include per-bundle aggregations in the result.
   *
   * @return array{promoted: list<mixed>, results: list<mixed>, total_hits: int, debug?: array<string, mixed>}
   *   The promoted hits, KNN results, total hit count, and optional debug
   *   payload keyed under 'debug' when $debug is TRUE.
   */
  private function executeFilteredSearch(array $knnQuery, string $model, bool $debug): array {
    $searchResult = $this->elasticClient->search([
      'index' => $knnQuery['index'],
      'body' => $knnQuery['body'],
    ]);
    assert($searchResult instanceof Elasticsearch);
    $knnResponse = $searchResult->asArray();

    $result = [
      'promoted' => [],
      'results' => $this->queryBuilder->parseKnnHits($knnResponse, $model),
      'total_hits' => $knnResponse['hits']['total']['value'] ?? 0,
    ];
    if ($debug) {
      $result['debug'] = ['bundles' => $this->queryBuilder->parseBundleAggregations($knnResponse)];
    }
    return $result;
  }

  /**
   * Run the msearch() path that blends promotions with KNN results.
   *
   * @param array<string, mixed> $knnQuery
   *   The pre-built KNN query (index + body).
   * @param string $query
   *   The user-supplied search query string.
   * @param string $language
   *   The active language code.
   * @param string $model
   *   The embedding model name.
   * @param bool $debug
   *   Whether to include per-bundle aggregations in the result.
   *
   * @return array{promoted: list<mixed>, results: list<mixed>, total_hits: int, debug?: array<string, mixed>}
   *   The promoted hits, KNN results, total hit count, and optional debug
   *   payload keyed under 'debug' when $debug is TRUE.
   */
  private function executeBlendedSearch(array $knnQuery, string $query, string $language, string $model, bool $debug): array {
    $promotionQuery = $this->queryBuilder->buildPromotionQuery($query, $language);

    $msearchResult = $this->elasticClient->msearch([
      'body' => [
        ['index' => $promotionQuery['index']],
        $promotionQuery['body'],
        ['index' => $knnQuery['index']],
        $knnQuery['body'],
      ],
    ]);
    assert($msearchResult instanceof Elasticsearch);
    $responses = $msearchResult->asArray()['responses'] ?? [];

    $promoted = isset($responses[0]['error'])
      ? []
      : $this->queryBuilder->parsePromotionHits($responses[0] ?? []);

    $knnResponse = isset($responses[1]['error']) ? [] : ($responses[1] ?? []);
    $result = [
      'promoted' => $promoted,
      'results' => $this->queryBuilder->parseKnnHits($knnResponse, $model),
      'total_hits' => $knnResponse['hits']['total']['value'] ?? 0,
    ];
    if ($debug && !isset($responses[1]['error'])) {
      $result['debug'] = ['bundles' => $this->queryBuilder->parseBundleAggregations($knnResponse)];
    }
    return $result;
  }

  /**
   * Assemble the JSON response with cache headers.
   *
   * @param array{promoted: list<mixed>, results: list<mixed>, total_hits: int, debug?: array<string, mixed>} $result
   *   The search result payload from one of the execute*() helpers.
   * @param int $page
   *   The 1-based page number echoed back to the client.
   * @param int $size
   *   The page size echoed back to the client.
   */
  private function createResponse(array $result, int $page, int $size): JsonResponse {
    $payload = [
      'promoted' => $result['promoted'],
      'results' => $result['results'],
      'page' => $page,
      'size' => $size,
      'total_hits' => $result['total_hits'],
    ];
    if (isset($result['debug'])) {
      $payload['debug'] = $result['debug'];
    }

    $response = new JsonResponse($payload);
    // The search form submits the same query on page reload, while the
    // response for a query is unlikely to change. Prevent hitting the API if
    // the user refreshes the page. The cardinality is too high to make a
    // public cache worthwhile.
    $response->setPrivate();
    $response->setMaxAge(600);
    return $response;
  }

}
