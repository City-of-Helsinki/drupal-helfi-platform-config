<?php

declare(strict_types=1);

namespace Drupal\helfi_recommendations;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Utility\Error;
use Drupal\Core\Url;
use Drupal\elasticsearch_connector\Plugin\search_api\backend\ElasticSearchBackend;
use Drupal\helfi_api_base\ApiClient\ApiClient;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\search_api\Entity\Index;
use Elastic\Elasticsearch\Exception\ElasticsearchException;
use Elastic\Transport\Exception\TransportException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The recommendation manager.
 */
class RecommendationManager {

  const INDEX_NAME = 'suggestions';

  /**
   * The constructor.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\helfi_api_base\Environment\EnvironmentResolverInterface $environmentResolver
   *   The environment resolver.
   * @param \Drupal\helfi_recommendations\TopicsManagerInterface $topicsManager
   *   The topics manager.
   * @param \Drupal\helfi_api_base\ApiClient\ApiClient $instanceApiClient
   *   The instance API client.
   */
  public function __construct(
    #[Autowire(service: 'logger.channel.helfi_recommendations')]
    private readonly LoggerInterface $logger,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EnvironmentResolverInterface $environmentResolver,
    private readonly TopicsManagerInterface $topicsManager,
    #[Autowire(service: 'helfi_recommendations.instance_api_client')] private ApiClient $instanceApiClient,
  ) {
  }

  /**
   * Get recommendations for a node.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The node.
   * @param int $limit
   *   How many recommendations should be returned.
   * @param string|null $target_langcode
   *   Which translation to use to select the recommendations,
   *   null uses the entity's translation.
   *
   * @return array
   *   Array of recommendations.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getRecommendations(ContentEntityInterface $entity, int $limit = 3, ?string $target_langcode = NULL): array {
    $data = [];
    $destination_langcode = $entity->language()->getId();
    $target_langcode = $target_langcode ?? $destination_langcode;
    if ($entity instanceof TranslatableInterface && !$entity->hasTranslation($target_langcode)) {
      $target_langcode = $destination_langcode;
    }

    // Get results from Elasticsearch.
    $query = $this->getElasticQuery($entity, $target_langcode, $limit + 10);
    $results = $this->searchElastic($query);

    // Fetch node data for each result via a JSON:API request.
    $data = $this->fetchNodeData($results, $target_langcode, $limit);

    return $data;
  }

  /**
   * Get the parent instance.
   *
   * @return string|null
   *   The parent instance.
   */
  private function getParentInstance(): ?string {
    $project = NULL;
    try {
      $project = $this->environmentResolver->getActiveProject()->getName();
    }
    catch (\InvalidArgumentException $e) {
      Error::logException($this->logger, $e);
    }

    return $project;
  }

  /**
   * Get the Elasticsearch query.
   *
   * This query aims to find results that have similar keywords to current
   * entity. Final calculated score is a sum of keyword matches, where each
   * matching keyword is scored based on the similarity of their saved score
   * values (as provided by the annif service).
   *
   * As a base reference an entity with identical content (and identical
   * keywords as a result) would have the highest score.
   *
   * Example:
   * - Current entity has keywords: [keyword1, keyword2, keyword3] with scores:
   *   [1, 0.5, 0.2]
   * - Search result A has keywords: [keyword1, keyword2, keyword3] with scores:
   *   [0.9, 0.6, 0.3]
   * - Search result B has keywords: [keyword1, keyword2, keyword3] with scores:
   *   [0.5, 0.8, 0.1]
   *
   * Both search results have the same matching keywords, but result A has
   * score values closer to the current entity's score values, so it should
   * be ranked higher.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param string $target_langcode
   *   Which translation to use to select the recommendations.
   * @param int $limit
   *   How many recommendations should be returned. Defaults to 3.
   *
   * @return array
   *   The elastic query.
   */
  private function getElasticQuery(ContentEntityInterface $entity, string $target_langcode, int $limit = 3): array {
    // Build keyword terms and score functions.
    $keywords = $this->topicsManager->getKeywords($entity);
    $keyword_terms = [];
    $keyword_score_functions = [];
    foreach ($keywords as $keyword) {
      $keyword_terms[] = [
        'term' => [
          'keywords.label' => $keyword['label'],
        ],
      ];
      $keyword_score_functions[] = [
        'filter' => [
          'term' => [
            'keywords.label' => $keyword['label'],
          ],
        ],
        'script_score' => [
          'script' => [
            'source' => "decayNumericLinear(params.origin, params.scale, params.offset, params.decay, doc['keywords.score'].value * 100)",
            'params' => [
              'origin' => $keyword['score'] * 100,
              'scale' => 10,
              'decay' => 0.9,
              'offset' => 10,
            ],
          ],
        ],
        'weight' => $keyword['score'] * 100,
      ];
    }

    // Build and return query.
    return [
      'from' => 0,
      'size' => $limit,
      'query' => [
        'bool' => [
          'filter' => [
            [
              'term' => [
                // Only include node results.
                // @todo Maybe TPR-entities as well?
                'parent_type' => 'node',
              ],
            ],
            [
              'term' => [
                'parent_translations' => $target_langcode,
              ],
            ],
          ],
          'must' => [
            [
              'exists' => [
                'field' => 'parent_id',
              ],
            ],
            [
              'exists' => [
                'field' => 'parent_instance',
              ],
            ],
            [
              'exists' => [
                'field' => 'parent_type',
              ],
            ],
            [
              'exists' => [
                'field' => 'parent_bundle',
              ],
            ],
            [
              'nested' => [
                'path' => 'keywords',
                'score_mode' => 'sum',
                'query' => [
                  'function_score' => [
                    'query' => [
                      'bool' => [
                        'should' => $keyword_terms,
                      ],
                    ],
                    'functions' => $keyword_score_functions,
                    'score_mode' => 'sum',
                    'boost_mode' => 'replace',
                  ],
                ],
              ],
            ],
          ],
          // Exclude current entity from results.
          'must_not' => [
            [
              'bool' => [
                'must' => [
                  [
                    'term' => [
                      'parent_id' => $entity->id(),
                    ],
                  ],
                  [
                    'term' => [
                      'parent_instance' => $this->getParentInstance(),
                    ],
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * Search Elasticsearch.
   *
   * @param array $query
   *   The query.
   *
   * @return array
   *   The search results.
   */
  private function searchElastic(array $query) : array {
    // Load the index.
    $index = Index::load(self::INDEX_NAME);
    $server = $index->getServerInstance();
    $backend = $server->getBackend();

    // This only works with an Elasticsearch backend.
    if (!$backend instanceof ElasticSearchBackend) {
      return [];
    }

    /** @var \Drupal\elasticsearch_connector\Plugin\search_api\backend\ElasticSearchBackend $backend */
    $client = $backend->getClient();

    $results = [];
    try {
      $results = $client->search([
        'index' => self::INDEX_NAME,
        'body' => $query,
      ])?->asArray() ?? [];
    }
    catch (ElasticsearchException | TransportException $e) {
      Error::logException($this->logger, $e);
    }

    return $results;
  }

  /**
   * Sort query result by created time.
   *
   * @param array $results
   *   Query results to sort.
   */
  private function sortByCreatedAt(array &$results) : void {
    usort($results, function ($a, $b) {
      if ($a->created == $b->created) {
        return 0;
      }
      return ($a->created > $b->created) ? -1 : 1;
    });
  }

  /**
   * Fetch node data.
   *
   * @param array $results
   *   The search results.
   * @param string $target_langcode
   *   The target language code.
   * @param int $limit
   *   The result amount limit.
   *
   * @return array
   *   The node data.
   */
  private function fetchNodeData(array $results, string $target_langcode, int $limit) : array {
    if (empty($results['hits']['hits'])) {
      return [];
    }

    $node_data = [];

    foreach ($results['hits']['hits'] as $hit) {
      $instance = !empty($hit['_source']['parent_instance']) ? reset($hit['_source']['parent_instance']) : NULL;
      $type = !empty($hit['_source']['parent_type']) ? reset($hit['_source']['parent_type']) : NULL;
      $bundle = !empty($hit['_source']['parent_bundle']) ? reset($hit['_source']['parent_bundle']) : NULL;
      $id = !empty($hit['_source']['parent_id']) ? reset($hit['_source']['parent_id']) : NULL;

      // We need all this in order to continue.
      if (!$instance || !$type || !$bundle || !$id) {
        continue;
      }

      $data = $instance === $this->getParentInstance()
        ? $this->buildLocalNodeData($type, $bundle, $id, $target_langcode)
        : $this->buildRemoteNodeData($instance, $type, $bundle, $id, $target_langcode);

      if ($data) {
        $node_data[] = $data;
      }

      if (count($node_data) >= $limit) {
        break;
      }
    }

    return $node_data;
  }

  /**
   * Build local node data.
   *
   * @param string $type
   *   The entity type.
   * @param string $bundle
   *   The entity bundle.
   * @param string $id
   *   The entity ID.
   * @param string $target_langcode
   *   The target language code.
   *
   * @return array
   *   The node data.
   */
  private function buildLocalNodeData(string $type, string $bundle, string $id, string $target_langcode) : array {
    $data = [];
    $entity = $this->entityTypeManager->getStorage($type)->load($id);

    if ($entity instanceof TranslatableInterface) {
      if (!$entity->hasTranslation($target_langcode)) {
        return $data;
      }

      $entity = $entity->getTranslation($target_langcode);
    }

    if ($entity instanceof EntityInterface) {
      $data['title'] = $entity->label();
      $data['url'] = $entity->toUrl();
    }

    return $data;
  }

  /**
   * Build remote node data.
   *
   * @param string $instance
   *   The instance name.
   * @param string $type
   *   The entity type.
   * @param string $bundle
   *   The entity bundle.
   * @param string $id
   *   The entity ID.
   * @param string $target_langcode
   *   The target language code.
   *
   * @return array
   *   The node data.
   */
  private function buildRemoteNodeData(string $instance, string $type, string $bundle, string $id, string $target_langcode) : array {
    $environment = $this->environmentResolver->getEnvironment($instance, $this->environmentResolver->getActiveEnvironmentName());
    $base_url = sprintf('%s/jsonapi/%s/%s', $environment->getInternalAddress($target_langcode), $type, $bundle);
    $url = Url::fromUri($base_url, [
      'query' => [
        'filter[drupal_internal__nid]' => $id,
        'filter[langcode]' => $target_langcode,
      ],
    ]);

    try {
      $response = $this->instanceApiClient->makeRequest('GET', $url->toString());
    }
    catch (\Exception $e) {
      Error::logException($this->logger, $e);
      return [];
    }

    $json = $response->data->data ? reset($response->data->data) : NULL;
    if (!$json) {
      return [];
    }

    $attributes = $json->attributes ?? NULL;
    if (!$attributes) {
      return [];
    }

    $data['title'] = $attributes->title ?? NULL;
    $data['url'] = $attributes->path->alias ? sprintf('%s/%s', $environment->getUrl($target_langcode), ltrim($attributes->path->alias, '/')) : NULL;

    return $data;
  }

}
