<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Flood\FloodInterface;
use Drupal\helfi_search\EmbeddingsModelException;
use Drupal\helfi_search\EmbeddingsModelInterface;
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

  const string INDEX_NAME = 'embeddings';

  const string FLOOD_EVENT = 'helfi_search.semantic_search';
  const int FLOOD_THRESHOLD = 100;
  const int FLOOD_WINDOW = 3600;

  const int MIN_QUERY_LENGTH = 3;
  const int MAX_QUERY_LENGTH = 500;

  public function __construct(
    private readonly EmbeddingsModelInterface $embeddingsModel,
    private readonly FloodInterface $flood,
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

      $results = $this->elasticClient->search([
        'index' => self::INDEX_NAME,
        'body' => [
          'knn' => [
            'field' => 'embeddings.vector',
            'query_vector' => $embeddings,
            'k' => 10,
            'num_candidates' => 100,
            'filter' => [
              'term' => [
                'search_api_language' => $currentLanguage,
              ],
            ],
          ],
          'size' => 10,
          '_source' => [
            'entity_type',
            'url',
            'label',
            'search_api_language',
          ],
        ],
      ])?->asArray() ?? [];

      $searchResults = [];
      foreach ($results['hits']['hits'] ?? [] as $hit) {
        $searchResults[] = [
          'score' => $hit['_score'] ?? 0,
          'entity_type' => array_first($hit['_source']['entity_type'] ?? []),
          'url' => array_first($hit['_source']['url'] ?? []),
          'title' => array_first($hit['_source']['label'] ?? []),
          'language' => array_first($hit['_source']['search_api_language'] ?? []),
        ];
      }

      return new JsonResponse(['results' => $searchResults]);
    }
    catch (EmbeddingsModelException | ElasticsearchException | TransportException) {
      return new JsonResponse(
        ['error' => 'Search service temporarily unavailable.'],
        503,
      );
    }
  }

}
