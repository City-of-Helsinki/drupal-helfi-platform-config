<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Flood\FloodInterface;
use Drupal\helfi_search\EmbeddingsModelException;
use Drupal\helfi_search\EmbeddingsModelInterface;
use Drupal\helfi_search\QueryBuilder;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ElasticsearchException;
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

  public function __construct(
    private readonly EmbeddingsModelInterface $embeddingsModel,
    private readonly FloodInterface $flood,
    private readonly QueryBuilder $queryBuilder,
    #[Autowire(service: 'helfi_platform_config.etusivu_elastic_client')]
    private readonly Client $elasticClient,
  ) {
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

      $bundles = array_filter(
        array_map('trim', explode(',', $request->query->getString('bundle'))),
      ) ?: NULL;

      $page = max(1, $request->query->getInt('page', 1));
      $size = min(QueryBuilder::KNN_MAX_SIZE, max(1, $request->query->getInt('size', QueryBuilder::KNN_DEFAULT_SIZE)));
      $from = ($page - 1) * $size;

      $knnQuery = $this->queryBuilder->buildKnnQuery($embeddings, $currentLanguage, $model, bundles: $bundles, size: $size, from: $from);

      $promoted = [];
      $searchResults = [];
      $totalHits = 0;

      if ($bundles) {
        $knnResponse = $this->elasticClient->search([
          'index' => $knnQuery['index'],
          'body' => $knnQuery['body'],
        ])?->asArray() ?? [];

        $searchResults = $this->queryBuilder->parseKnnHits($knnResponse, $model);
        $totalHits = $knnResponse['hits']['total']['value'] ?? 0;
      }
      else {
        $promotionQuery = $this->queryBuilder->buildPromotionQuery($query, $currentLanguage);

        $msearchResponse = $this->elasticClient->msearch([
          'body' => [
            ['index' => $promotionQuery['index']],
            $promotionQuery['body'],
            ['index' => $knnQuery['index']],
            $knnQuery['body'],
          ],
        ])?->asArray() ?? [];

        $responses = $msearchResponse['responses'] ?? [];

        if (!isset($responses[0]['error'])) {
          $promoted = $this->queryBuilder->parsePromotionHits($responses[0] ?? []);
        }

        if (!isset($responses[1]['error'])) {
          $searchResults = $this->queryBuilder->parseKnnHits($responses[1] ?? [], $model);
          $totalHits = $responses[1]['hits']['total']['value'] ?? 0;
        }
      }

      return new JsonResponse([
        'promoted' => $promoted,
        'results' => $searchResults,
        'page' => $page,
        'size' => $size,
        'total_hits' => $totalHits,
      ]);
    }
    catch (EmbeddingsModelException | ElasticsearchException | TransportException) {
      return new JsonResponse(
        ['error' => 'Search service temporarily unavailable.'],
        503,
      );
    }
  }

}
