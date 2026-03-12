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

    if (!$this->flood->isAllowed(self::FLOOD_EVENT, self::FLOOD_THRESHOLD, self::FLOOD_WINDOW)) {
      return new JsonResponse(
        ['error' => 'Too many requests. Please try again later.'],
        429,
      );
    }

    try {
      $embeddings = $this->embeddingsModel->getEmbedding($query);

      // Register flood after the expensive embedding API call.
      $this->flood->register(self::FLOOD_EVENT, self::FLOOD_WINDOW);

      $currentLanguage = $this->languageManager()->getCurrentLanguage()->getId();

      $promotionQuery = $this->queryBuilder->buildPromotionQuery($query, $currentLanguage);
      $knnQuery = $this->queryBuilder->buildKnnQuery($embeddings, $currentLanguage);

      // Execute both queries in a single HTTP round-trip using
      // ES Multi Search API. The response order matches the request order:
      // responses[0] = promoted results, responses[1] = KNN results.
      $msearchResponse = $this->elasticClient->msearch([
        'body' => [
          ['index' => $promotionQuery['index']],
          $promotionQuery['body'],
          ['index' => $knnQuery['index']],
          $knnQuery['body'],
        ],
      ])?->asArray() ?? [];

      $responses = $msearchResponse['responses'] ?? [];

      $promoted = [];
      if (!isset($responses[0]['error'])) {
        $promoted = $this->queryBuilder->parsePromotionHits($responses[0] ?? []);
      }

      $searchResults = [];
      if (!isset($responses[1]['error'])) {
        $searchResults = $this->queryBuilder->parseKnnHits($responses[1] ?? []);
      }

      return new JsonResponse([
        'promoted' => $promoted,
        'results' => $searchResults,
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
