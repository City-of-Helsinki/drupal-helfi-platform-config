<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Flood\FloodInterface;
use Drupal\helfi_search\EmbeddingModel;
use Drupal\helfi_search\EmbeddingApiException;
use Drupal\helfi_search\EmbeddingApiInterface;
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
 *
 * @phpstan-type SearchResult array{promoted: list<mixed>, contacts: list<mixed>, contacts_total: int, results: list<mixed>, total_hits: int, max_score: float|null, debug?: array<string, mixed>}
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

  // Bundle filter value the React form submits for the contacts filter.
  const string CONTACTS_SENTINEL = 'contacts';

  public function __construct(
    private readonly EmbeddingApiInterface $embeddingsModel,
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

    $page = max(1, $request->query->getInt('page', 1));
    $size = min(QueryBuilder::KNN_MAX_SIZE, max(1, $request->query->getInt('size', QueryBuilder::KNN_DEFAULT_SIZE)));
    $offset = ($page - 1) * $size;
    [$bundleFilter, $withContacts] = $this->parseBundleParameter($request);

    // The KNN search returns at most KNN_K results, so the later pages would
    // be empty. Contacts are paged before the content results, so with
    // contacts the limit is the Elasticsearch result window instead.
    $maxOffset = $withContacts ? QueryBuilder::MAX_RESULT_WINDOW - $size : QueryBuilder::KNN_K;
    if ($offset > $maxOffset) {
      return new JsonResponse(
        ['error' => 'Page is out of range.'],
        400,
      );
    }

    // The debug output returns every matching chunk, so it's only available
    // for the users who can tune the search.
    $debug = $request->query->getBoolean('debug') && $this->currentUser()->hasPermission('administer helfi_search');

    if (!$this->flood->isAllowed(self::FLOOD_EVENT, self::FLOOD_THRESHOLD, self::FLOOD_WINDOW)) {
      return new JsonResponse(
        ['error' => 'Too many requests. Please try again later.'],
        429,
      );
    }

    // Apply rewrite rules to query before embedding, so short
    // queries tokenize the same way they do in indexed content.
    $query = QueryRewriter::rewrite($query, $this->config('helfi_search.settings')->get('canonical_terms') ?? []);

    $model = $this->resolveModel($request);
    $currentLanguage = $this->languageManager()->getCurrentLanguage()->getId();

    try {
      $contactsPage = $withContacts ? $this->searchContacts($query, $currentLanguage, $offset, $size) : NULL;

      // Only contacts were requested, so the embedding API call is not needed.
      if ($contactsPage !== NULL && !$bundleFilter) {
        return $this->createResponse([
          'promoted' => [],
          'contacts' => $contactsPage['contacts'],
          'contacts_total' => $contactsPage['total'],
          'results' => [],
          'total_hits' => $contactsPage['total'],
          'max_score' => NULL,
        ], $page, $size);
      }

      // Register a flood event before allowing the expensive embedding API
      // call. This trips flood protection even if the embedding API call
      // fails.
      $this->flood->register(self::FLOOD_EVENT, self::FLOOD_WINDOW);

      $embeddings = $this->embeddingsModel->getEmbedding($query, $model);

      [$bundles, $excludeBundles] = $this->resolveBundleFilters($bundleFilter);

      // Contacts are listed before the content results, so the content
      // results start where the contacts end.
      $contentOffset = $contactsPage ? max(0, $offset - $contactsPage['total']) : $offset;
      $contentSize = $contactsPage ? $size - count($contactsPage['contacts']) : $size;

      $knnQuery = $this->queryBuilder->buildKnnQuery(
        $embeddings,
        $currentLanguage,
        $model,
        bundles: $bundles,
        size: $contentSize,
        from: $contentOffset,
        excludeBundles: $excludeBundles,
        // Debug-only: ask ES to return every matching chunk so per-chunk
        // scores can be inspected.
        innerHitsSize: $debug ? QueryBuilder::KNN_MAX_SIZE : 1,
        includeAggregations: $debug,
      );

      $result = ($bundles || $excludeBundles)
        ? $this->executeFilteredSearch($knnQuery, $model, $debug)
        : $this->executeBlendedSearch($knnQuery, $query, $currentLanguage, $model, $debug, withContacts: $contactsPage === NULL);

      if ($contactsPage !== NULL) {
        $result['contacts'] = $contactsPage['contacts'];
        $result['contacts_total'] = $contactsPage['total'];
        $result['total_hits'] += $contactsPage['total'];
      }

      return $this->createResponse($result, $page, $size);
    }
    catch (EmbeddingApiException | ElasticsearchException | TransportException) {
      return new JsonResponse(
        ['error' => 'Search service temporarily unavailable.'],
        503,
      );
    }
  }

  /**
   * Get the model to use, from query parameter or default model.
   *
   * The optional 'model' query parameter is honored only when it names one of
   * the models enabled in the 'openai_models' config; otherwise the request
   * falls back to the default model, which is always available.
   */
  private function resolveModel(Request $request): EmbeddingModel {
    $requested = EmbeddingModel::tryFrom($request->query->getString('model'));

    if ($requested && in_array($requested, EmbeddingModel::ENABLED, TRUE)) {
      return $requested;
    }

    return EmbeddingModel::DEFAULT;
  }

  /**
   * Split the 'bundle' query parameter into content bundles and contacts.
   *
   * The 'contacts' value means the Contacts filter is on. Contacts are not
   * content, so the value is removed from the content filters.
   *
   * @return array{0: list<string>, 1: bool}
   *   [$bundles, $withContacts].
   */
  private function parseBundleParameter(Request $request): array {
    $bundles = array_values(array_filter(
      array_map('trim', explode(',', $request->query->getString('bundle'))),
    ));
    $withContacts = in_array(self::CONTACTS_SENTINEL, $bundles, TRUE);

    return [array_values(array_diff($bundles, [self::CONTACTS_SENTINEL])), $withContacts];
  }

  /**
   * Resolve the bundle inclusion/exclusion filters.
   *
   * The React form uses two sentinels:
   * - 'news' expands to all configured news bundles.
   * - 'others' means "everything except news bundles".
   *
   * When both are selected (or 'others' is combined with an explicit news
   * bundle) the include and exclude cancel out, so all bundle filtering is
   * dropped.
   *
   * @param list<string> $bundles
   *   The requested content bundles.
   *
   * @return array{0: list<string>|null, 1: list<string>|null}
   *   [$bundles, $excludeBundles], each side NULL when not constrained.
   */
  private function resolveBundleFilters(array $bundles): array {
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
   * Fetch one page of contacts and the total number of matching contacts.
   *
   * @return array{contacts: list<mixed>, total: int}
   *   The contacts on the page and the total hit count.
   *
   * @throws \Elastic\Elasticsearch\Exception\ElasticsearchException
   * @throws \Elastic\Transport\Exception\TransportException
   */
  private function searchContacts(string $query, string $language, int $from, int $size): array {
    if ($this->currentUser()->isAnonymous()) {
      // @todo https://helsinkisolutionoffice.atlassian.net/browse/UHF-13271.
      return ['contacts' => [], 'total' => 0];
    }

    $contactQuery = $this->queryBuilder->buildContactQuery($query, $language, $size, $from);
    $searchResult = $this->elasticClient->search([
      'index' => $contactQuery['index'],
      'body' => $contactQuery['body'],
    ]);
    assert($searchResult instanceof Elasticsearch);
    $response = $searchResult->asArray();

    return [
      'contacts' => $this->queryBuilder->parseContactHits($response),
      'total' => (int) ($response['hits']['total']['value'] ?? 0),
    ];
  }

  /**
   * Run the single-search() path used when a bundle filter is in effect.
   *
   * @param array<string, mixed> $knnQuery
   *   The pre-built KNN query (index + body).
   * @param \Drupal\helfi_search\EmbeddingModel $model
   *   The embedding model to use.
   * @param bool $debug
   *   Whether to include per-bundle aggregations in the result.
   *
   * @return SearchResult
   *   The promoted hits, KNN results, total hit count, and optional debug
   *   payload keyed under 'debug' when $debug is TRUE.
   *
   * @throws \Elastic\Elasticsearch\Exception\ElasticsearchException
   * @throws \Elastic\Transport\Exception\TransportException
   */
  private function executeFilteredSearch(array $knnQuery, EmbeddingModel $model, bool $debug): array {
    $searchResult = $this->elasticClient->search([
      'index' => $knnQuery['index'],
      'body' => $knnQuery['body'],
    ]);
    assert($searchResult instanceof Elasticsearch);
    $knnResponse = $searchResult->asArray();

    $result = [
      'promoted' => [],
      'contacts' => [],
      'contacts_total' => 0,
      'results' => $this->queryBuilder->parseKnnHits($knnResponse, $model),
      'total_hits' => (int) ($knnResponse['hits']['total']['value'] ?? 0),
      'max_score' => isset($knnResponse['hits']['max_score'])
        ? (float) $knnResponse['hits']['max_score']
        : NULL,
    ];
    if ($debug) {
      $result['debug'] = ['bundles' => $this->queryBuilder->parseBundleAggregations($knnResponse)];
    }
    return $result;
  }

  /**
   * Run the msearch() path that blends KNN hits with other results.
   *
   * @param array<string, mixed> $knnQuery
   *   The pre-built KNN query (index + body).
   * @param string $query
   *   The user-supplied search query string.
   * @param string $language
   *   The active language code.
   * @param \Drupal\helfi_search\EmbeddingModel $model
   *   The embedding model to use.
   * @param bool $debug
   *   Whether to include per-bundle aggregations in the result.
   * @param bool $withContacts
   *   Whether to run the contact sub-query. The caller skips it when it
   *   has already fetched the contacts.
   *
   * @return SearchResult
   *   The promoted hits, contacts, KNN results, total hit count, and optional
   *   debug payload keyed under 'debug' when $debug is TRUE.
   *
   * @throws \Elastic\Elasticsearch\Exception\ElasticsearchException
   * @throws \Elastic\Transport\Exception\TransportException
   */
  private function executeBlendedSearch(array $knnQuery, string $query, string $language, EmbeddingModel $model, bool $debug, bool $withContacts = TRUE): array {
    $promotionQuery = $this->queryBuilder->buildPromotionQuery($query, $language);

    $body = [
      ['index' => $promotionQuery['index']],
      $promotionQuery['body'],
      ['index' => $knnQuery['index']],
      $knnQuery['body'],
    ];
    // Contacts are never shown to anonymous users.
    // @todo https://helsinkisolutionoffice.atlassian.net/browse/UHF-13271.
    if ($withContacts && !$this->currentUser()->isAnonymous()) {
      $contactQuery = $this->queryBuilder->buildContactQuery($query, $language);
      $body[] = ['index' => $contactQuery['index']];
      $body[] = $contactQuery['body'];
    }

    $msearchResult = $this->elasticClient->msearch(['body' => $body]);
    assert($msearchResult instanceof Elasticsearch);
    $responses = $msearchResult->asArray()['responses'] ?? [];

    $promoted = isset($responses[0]['error'])
      ? []
      : $this->queryBuilder->parsePromotionHits($responses[0] ?? []);

    $contactResponse = isset($responses[2]['error']) ? [] : ($responses[2] ?? []);

    $knnResponse = isset($responses[1]['error']) ? [] : ($responses[1] ?? []);
    $result = [
      'promoted' => $promoted,
      'contacts' => $this->queryBuilder->parseContactHits($contactResponse),
      'contacts_total' => (int) ($contactResponse['hits']['total']['value'] ?? 0),
      'results' => $this->queryBuilder->parseKnnHits($knnResponse, $model),
      'total_hits' => (int) ($knnResponse['hits']['total']['value'] ?? 0) + count($promoted),
      'max_score' => isset($knnResponse['hits']['max_score'])
        ? (float) $knnResponse['hits']['max_score']
        : NULL,
    ];
    if ($debug && !isset($responses[1]['error'])) {
      $result['debug'] = ['bundles' => $this->queryBuilder->parseBundleAggregations($knnResponse)];
    }
    return $result;
  }

  /**
   * Tells whether the best hit of this page misses the relevancy threshold.
   *
   * @param SearchResult $result
   *   The search result payload from one of the execute*() helpers.
   */
  private function isLowRelevance(array $result): bool {
    if ($result['promoted'] || $result['contacts']) {
      return FALSE;
    }

    $threshold = (float) ($this->config('helfi_search.settings')->get('low_relevance_threshold') ?? 0);

    return $result['max_score'] === NULL || $result['max_score'] < $threshold;
  }

  /**
   * Assemble the JSON response with cache headers.
   *
   * @param SearchResult $result
   *   The search result payload from one of the execute*() helpers.
   * @param int $page
   *   The 1-based page number echoed back to the client.
   * @param int $size
   *   The page size echoed back to the client.
   */
  private function createResponse(array $result, int $page, int $size): JsonResponse {
    $payload = [
      'promoted' => $result['promoted'],
      'contacts' => $result['contacts'],
      'contacts_total' => $result['contacts_total'],
      'results' => $result['results'],
      'page' => $page,
      'size' => $size,
      'total_hits' => $result['total_hits'],
      'low_relevance' => $this->isLowRelevance($result),
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
