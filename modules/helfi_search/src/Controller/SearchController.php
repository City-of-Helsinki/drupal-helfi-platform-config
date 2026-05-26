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

    try {
      $embeddings = $this->embeddingsModel->getEmbedding($query, $model);

      // Register flood after the expensive embedding API call.
      $this->flood->register(self::FLOOD_EVENT, self::FLOOD_WINDOW);

      $currentLanguage = $this->languageManager()->getCurrentLanguage()->getId();

      $bundles = array_values(array_filter(
        array_map('trim', explode(',', $request->query->getString('bundle'))),
      ));

      // The 'others' sentinel from the React form means "everything except
      // news bundles". When combined with a news bundle the include and
      // exclude cancel out, so drop all bundle filtering.
      $excludeBundles = NULL;
      if (in_array('others', $bundles, TRUE)) {
        $bundles = array_values(array_filter($bundles, static fn (string $b): bool => $b !== 'others'));
        if (array_intersect($bundles, self::NEWS_BUNDLES)) {
          $bundles = [];
        }
        else {
          $excludeBundles = self::NEWS_BUNDLES;
        }
      }
      $bundles = $bundles ?: NULL;

      $page = max(1, $request->query->getInt('page', 1));
      $size = min(QueryBuilder::KNN_MAX_SIZE, max(1, $request->query->getInt('size', QueryBuilder::KNN_DEFAULT_SIZE)));
      $from = ($page - 1) * $size;

      $debug = $request->query->getBoolean('debug') && $this->isDebugAllowed();

      // Debug-only: ask ES to return every matching chunk so per-chunk scores
      // can be inspected.
      $innerHitsSize = $debug ? QueryBuilder::KNN_MAX_SIZE : 1;

      $knnQuery = $this->queryBuilder->buildKnnQuery(
        $embeddings,
        $currentLanguage,
        $model,
        bundles: $bundles,
        size: $size,
        from: $from,
        excludeBundles: $excludeBundles,
        innerHitsSize: $innerHitsSize,
        includeAggregations: $debug,
      );

      $promoted = [];
      $searchResults = [];
      $totalHits = 0;
      $bundleAggregations = NULL;

      if ($bundles || $excludeBundles) {
        $searchResult = $this->elasticClient->search([
          'index' => $knnQuery['index'],
          'body' => $knnQuery['body'],
        ]);
        assert($searchResult instanceof Elasticsearch);
        $knnResponse = $searchResult->asArray();

        $searchResults = $this->queryBuilder->parseKnnHits($knnResponse, $model);
        $totalHits = $knnResponse['hits']['total']['value'] ?? 0;
        if ($debug) {
          $bundleAggregations = $this->queryBuilder->parseBundleAggregations($knnResponse);
        }
      }
      else {
        $promotionQuery = $this->queryBuilder->buildPromotionQuery($query, $currentLanguage);

        $msearchResult = $this->elasticClient->msearch([
          'body' => [
            ['index' => $promotionQuery['index']],
            $promotionQuery['body'],
            ['index' => $knnQuery['index']],
            $knnQuery['body'],
          ],
        ]);
        assert($msearchResult instanceof Elasticsearch);
        $msearchResponse = $msearchResult->asArray();

        $responses = $msearchResponse['responses'] ?? [];

        if (!isset($responses[0]['error'])) {
          $promoted = $this->queryBuilder->parsePromotionHits($responses[0] ?? []);
        }

        if (!isset($responses[1]['error'])) {
          $searchResults = $this->queryBuilder->parseKnnHits($responses[1] ?? [], $model);
          $totalHits = $responses[1]['hits']['total']['value'] ?? 0;
          if ($debug) {
            $bundleAggregations = $this->queryBuilder->parseBundleAggregations($responses[1] ?? []);
          }
        }
      }

      $payload = [
        'promoted' => $promoted,
        'results' => $searchResults,
        'page' => $page,
        'size' => $size,
        'total_hits' => $totalHits,
      ];
      if ($bundleAggregations !== NULL) {
        $payload['debug'] = ['bundles' => $bundleAggregations];
      }

      $response = new JsonResponse($payload);

      // The search form submits the same query on page reload, while
      // the response for a query is unlikely to change. Prevent hitting
      // the API if the user refreshes the page. However, the cardinality
      // is so high that there is no point in caching these responses to
      // a public cache.
      $response->setPrivate();
      $response->setMaxAge(600);

      return $response;
    }
    catch (EmbeddingsModelException | ElasticsearchException | TransportException) {
      return new JsonResponse(
        ['error' => 'Search service temporarily unavailable.'],
        503,
      );
    }
  }

}
