<?php

declare(strict_types=1);

namespace Drupal\helfi_recommendations;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Utility\Error;
use Elastic\Elasticsearch\Exception\ElasticsearchException;
use Elastic\Transport\Exception\TransportException;
use Drupal\elasticsearch_connector\Plugin\search_api\backend\ElasticSearchBackend;
use Drupal\search_api\Entity\Index;
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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityManager
   *   The entity type manager.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityManager,
    #[Autowire(service: 'logger.channel.helfi_recommendations')]
    private readonly LoggerInterface $logger,
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
    $destination_langcode = $entity->language()->getId();
    $target_langcode = $target_langcode ?? $destination_langcode;
    if ($entity instanceof TranslatableInterface && !$entity->hasTranslation($target_langcode)) {
      $target_langcode = $destination_langcode;
    }

    // Get results from Elasticsearch.
    $query = $this->getElasticQuery($entity, $limit, $target_langcode);
    $results = $this->searchElastic($query);

    // Fetch node data for each result via a JSON:API request.
    // @todo: Implement this.

    return [];
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
   * - Current entity has keywords: [keyword1, keyword2, keyword3] with scores: [1, 0.5, 0.2]
   * - Search result A has keywords: [keyword1, keyword2, keyword3] with scores: [0.9, 0.6, 0.3]
   * - Search result B has keywords: [keyword1, keyword2, keyword3] with scores: [0.5, 0.8, 0.1]
   *
   * Both search results have the same matching keywords, but result A has
   * score values closer to the current entity's score values, so it should
   * be ranked higher.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param int $limit
   *   How many recommendations should be returned.
   * @param string|null $target_langcode
   *   Which translation to use to select the recommendations,
   *
   * @return array
   *   The elastic query.
   */
  private function getElasticQuery(ContentEntityInterface $entity, int $limit = 3, ?string $target_langcode = NULL): array {
    // Build keyword terms and score functions.
    $keyword_terms = [];
    $keyword_score_functions = [];
    try {
      if ($entity->hasField('field_recommendation_topics')) {
        foreach ($entity->field_recommendation_topics->entity->keywords as $keyword) {
          $keyword_terms[] = [
          'term' => [
            'keywords.label' => $keyword->entity->label(),
          ],
        ];
        $keyword_score_functions[] = [
          'filter' => [
            'term' => [
              'keywords.label' => $keyword->entity->label(),
            ],
          ],
          'script_score' => [
            'script' => [
              'source' => "decayNumericLinear(params.origin, params.scale, params.offset, params.decay, doc['keywords.score'].value * 1000)",
              'params' => [
                'origin' => $keyword->score * 1000,
                'scale' => 1,
                'decay' => 0.5,
                'offset' => 0,
              ],
            ],
            ],
          ];
        }
      }
    }
    catch (\Throwable $e) {
      Error::logException($this->logger, $e);
    }

    // Build and return query.
    return [
      'from' => 0,
      'size' => $limit,
      // Any item will get a score of 2, even without any matching keywords.
      // Let's make sure there's at least some resemblance to the current
      // entity.
      'min_score' => 2.01,
      'query' => [
        'bool' => [
          'filter' => [
            'term' => [
              // Only include node results.
              // @todo: Maybe TPR-entities as well?
              'parent_type' => 'node',
            ],
            'term' => [
              'parent_translations' => $target_langcode,
            ],
          ],
          'must' => [
            [
              // Parent ID is required.
              'exists' => [
                'field' => 'parent_id',
              ],
            ],
            [
              // Parent instance is required.
              'exists' => [
                'field' => 'parent_instance',
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
                      'parent_instance' => 'rekry',
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

    /** @var ElasticSearchBackend $backend */
    $client = $backend->getClient();

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

}
