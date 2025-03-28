<?php

declare(strict_types=1);

namespace Drupal\helfi_recommendations;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Utility\Error;
use Drupal\Core\Url;
use Drupal\helfi_api_base\ApiClient\ApiClient;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Elastic\Elasticsearch\Exception\ElasticsearchException;
use Elastic\Transport\Exception\TransportException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Elastic\Elasticsearch\Client;

/**
 * The recommendation manager.
 */
final class RecommendationManager implements RecommendationManagerInterface {

  const INDEX_NAME = 'suggestions';
  const ELASTICSEARCH_QUERY_BUFFER = 10;

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
   * @param \Drupal\helfi_api_base\ApiClient\ApiClient $jsonApiClient
   *   The JSON API client.
   * @param \Elastic\Elasticsearch\Client $elasticClient
   *   The Elasticsearch client.
   */
  public function __construct(
    #[Autowire(service: 'logger.channel.helfi_recommendations')]
    private readonly LoggerInterface $logger,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EnvironmentResolverInterface $environmentResolver,
    private readonly TopicsManagerInterface $topicsManager,
    #[Autowire(service: 'helfi_recommendations.json_api_client')] private ApiClient $jsonApiClient,
    #[Autowire(service: 'helfi_recommendations.elastic_client')] private Client $elasticClient,
  ) {
  }

  /**
   * {@inheritDoc}
   */
  public function showRecommendations(ContentEntityInterface $entity): bool {
    // List suggested_topics_reference fields that the entity has.
    $fields = array_filter(
      $entity->getFieldDefinitions(),
      static fn (FieldDefinitionInterface $definition) => $definition->getType() === 'suggested_topics_reference'
    );

    if (!$fields) {
      return FALSE;
    }

    // Check if any of the suggested topics reference fields have the show_block
    // property set to FALSE. If so, do not show recommendations.
    foreach ($fields as $key => $definition) {
      $field = $entity->get($key);
      assert($field instanceof EntityReferenceFieldItemListInterface);

      foreach ($field->getValue() as $value) {
        if (isset($value['show_block']) && !$value['show_block']) {
          return FALSE;
        }
      }
    }

    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function getRecommendations(ContentEntityInterface $entity, int $limit = 3, ?string $target_langcode = NULL): array {
    $data = [];
    $destination_langcode = $entity->language()->getId();
    $target_langcode = $target_langcode ?? $destination_langcode;
    if ($entity instanceof TranslatableInterface && !$entity->hasTranslation($target_langcode)) {
      $target_langcode = $destination_langcode;
    }

    // Get results from Elasticsearch. Fetch more than needed to account for
    // the fact that some results may not be available from json api anymore.
    $query = $this->getElasticQuery($entity, $target_langcode, $limit + self::ELASTICSEARCH_QUERY_BUFFER);
    $results = $query ? $this->searchElastic($query) : [];

    // Fetch node data for each result via a JSON:API request.
    $data = $results ? $this->fetchNodeData($results, $target_langcode, $limit) : [];

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
   * Get options from topics reference fields.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param string $property
   *   The property to get options from.
   *
   * @return array
   *   Array of enabled options.
   */
  private function getOptions(ContentEntityInterface $entity, string $property): array {
    $fields = $this->topicsManager->getTopicsReferenceFields($entity);
    $options = [];

    foreach ($fields as $field) {
      foreach ($field->getValue() as $value) {
        if (!isset($value[$property]) || !is_array($value[$property])) {
          continue;
        }

        $enabled_options = array_values(array_filter($value[$property], static fn (string $option) => !empty($option)));
        array_push($options, ...$enabled_options);
      }
    }

    return $options;
  }

  /**
   * Get enabled instances.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return array
   *   Array of enabled instances.
   */
  private function getEnabledInstances(ContentEntityInterface $entity): array {
    return $this->getOptions($entity, 'instances');
  }

  /**
   * Get enabled content types and bundles.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return array
   *   Array of enabled content types and bundles.
   */
  private function getEnabledContentTypesAndBundles(ContentEntityInterface $entity): array {
    $content_types = [];
    $options = $this->getOptions($entity, 'content_types');
    foreach ($options as $option) {
      $option_pair = explode('|', $option);
      $entity_type = $option_pair[0];
      $bundle = $option_pair[1];

      if (!isset($content_types[$entity_type])) {
        $content_types[$entity_type] = [];
      }

      $content_types[$entity_type][] = $bundle;
    }

    return $content_types;
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
    try {
      $keywords = $this->topicsManager->getKeywords($entity);
      if (!$keywords) {
        return [];
      }
    }
    catch (\Exception $e) {
      Error::logException($this->logger, $e);
      return [];
    }

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
    $query = [
      'from' => 0,
      'size' => $limit,
      'query' => [
        'bool' => [
          'filter' => [
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
              'exists' => [
                'field' => 'uuid',
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

    // Filter enabled instances.
    $allowed_instances = $this->getEnabledInstances($entity);
    if (!empty($allowed_instances)) {
      $terms_set = [
        'terms_set' => [
          'parent_instance' => [
            'terms' => $allowed_instances,
            'minimum_should_match' => 1,
          ],
        ],
      ];
      $query['query']['bool']['filter'][] = $terms_set;
    }

    // Filter enabled entity types and bundles.
    $allowed_content_types = $this->getEnabledContentTypesAndBundles($entity);
    if (!empty($allowed_content_types)) {
      $allowed_entity_types = [];
      $allowed_bundles = [];

      foreach ($allowed_content_types as $content_type => $bundles) {
        $allowed_entity_types[] = $content_type;
        array_push($allowed_bundles, ...$bundles);
      }

      if ($allowed_entity_types) {
        $terms_set = [
          'terms_set' => [
            'parent_type' => [
              'terms' => $allowed_entity_types,
              'minimum_should_match' => 1,
            ],
          ],
        ];
        $query['query']['bool']['filter'][] = $terms_set;
      }

      if ($allowed_bundles) {
        $terms_set = [
          'terms_set' => [
            'parent_bundle' => [
              'terms' => $allowed_bundles,
              'minimum_should_match' => 1,
            ],
          ],
        ];
        $query['query']['bool']['filter'][] = $terms_set;
      }
    }

    return $query;
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
    $results = [];
    try {
      $results = $this->elasticClient->search([
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
      $uuid = !empty($hit['_source']['uuid']) ? reset($hit['_source']['uuid']) : NULL;

      // We need all this in order to continue.
      if (!$instance || !$type || !$bundle || !$id || !$uuid) {
        continue;
      }

      $data = $instance === $this->getParentInstance()
        ? $this->buildLocalEntityData($type, $bundle, $id, $target_langcode)
        : $this->buildRemoteEntityData($instance, $type, $bundle, $id, $target_langcode);

      if ($data) {
        $node_data[] = ['uuid' => $uuid] + $data;
      }

      if (count($node_data) >= $limit) {
        break;
      }
    }

    return $node_data;
  }

  /**
   * Build local entity data.
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
   *   The entity data.
   */
  private function buildLocalEntityData(string $type, string $bundle, string $id, string $target_langcode) : array {
    $data = [];
    $entity = $this->entityTypeManager->getStorage($type)->load($id);

    if (!$entity->access('view')) {
      return [];
    }

    if ($entity instanceof TranslatableInterface) {
      if (!$entity->hasTranslation($target_langcode)) {
        return [];
      }
      $entity = $entity->getTranslation($target_langcode);
    }

    if ($entity instanceof EntityInterface) {
      $data['title'] = $entity->label();
      $data['url'] = $entity->toUrl('canonical', [
        'language' => $entity->language(),
      ]);
    }

    return $data;
  }

  /**
   * Build remote entity data.
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
   *   The entity data.
   */
  private function buildRemoteEntityData(string $instance, string $type, string $bundle, string $id, string $target_langcode) : array {
    $environment = $this->environmentResolver->getEnvironment($instance, $this->environmentResolver->getActiveEnvironmentName());

    // Use internal url for json api requests to avoid varnish caching issues.
    // This is also the only way for this to work in local environments.
    $base_url = sprintf('%s/jsonapi/%s/%s', $environment->getInternalAddress($target_langcode), $type, $bundle);
    $url = Url::fromUri($base_url, [
      'query' => [
        'filter[drupal_internal__nid]' => $id,
        'filter[langcode]' => $target_langcode,
      ],
    ]);

    try {
      $response = $this->jsonApiClient->makeRequest('GET', $url->toString());
    }
    catch (\Exception $e) {
      Error::logException($this->logger, $e);
      return [];
    }

    $json = !empty($response->data->data) && is_array($response->data->data) ? reset($response->data->data) : NULL;
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
