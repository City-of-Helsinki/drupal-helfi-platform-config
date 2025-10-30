<?php

declare(strict_types=1);

namespace Drupal\helfi_recommendations;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Utility\Error;
use Drupal\helfi_api_base\Cache\CacheTagInvalidatorInterface;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_api_base\Environment\ProjectRoleEnum;
use Drupal\helfi_api_base\Environment\Project;
use Elastic\Elasticsearch\Exception\ElasticsearchException;
use Elastic\Transport\Exception\TransportException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Elastic\Elasticsearch\Client;

/**
 * The recommendation managerplat.
 */
class RecommendationManager implements RecommendationManagerInterface {

  use StringTranslationTrait;

  public const INDEX_NAME = 'suggestions';
  public const EXTERNAL_CACHE_TAG_PREFIX = 'suggested_topics_uuid:';

  /**
   * The recommendations.
   *
   * @var array
   */
  private $recommendations;

  /**
   * The constructor.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\helfi_api_base\Environment\EnvironmentResolverInterface $environmentResolver
   *   The environment resolver.
   * @param \Drupal\helfi_recommendations\TopicsManagerInterface $topicsManager
   *   The topics manager.
   * @param \Elastic\Elasticsearch\Client $elasticClient
   *   The Elasticsearch client.
   * @param \Drupal\helfi_api_base\Cache\CacheTagInvalidatorInterface $cacheTagInvalidator
   *   The cache tag invalidator.
   * @param \Drupal\Core\State\StateInterface $state
   *   The State API.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $stringTranslation
   *   The string translation.
   */
  public function __construct(
    #[Autowire(service: 'logger.channel.helfi_recommendations')]
    private readonly LoggerInterface $logger,
    private readonly EnvironmentResolverInterface $environmentResolver,
    private readonly TopicsManagerInterface $topicsManager,
    #[Autowire(service: 'helfi_platform_config.etusivu_elastic_client')]
    private readonly Client $elasticClient,
    private readonly CacheTagInvalidatorInterface $cacheTagInvalidator,
    private readonly StateInterface $state,
    TranslationInterface $stringTranslation,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * {@inheritdoc}
   */
  public function ping(): bool {
    try {
      // Check if the index exists or not.
      $response = $this->elasticClient->indices()->exists([
        'index' => self::INDEX_NAME,
      ]);
      return $response->getStatusCode() === 200;
    }
    catch (ElasticsearchException | TransportException) {
    }
    return FALSE;
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
        if (isset($value['show_block'])) {
          return (bool) $value['show_block'];
        }
      }
    }

    // Return the default value for entities that do not yet have a value saved.
    return $this->state->get('helfi_recommendations.suggested_topics_default_show_block', TRUE);
  }

  /**
   * {@inheritDoc}
   */
  public function getRecommendations(ContentEntityInterface $entity, int $limit = 3, ?string $target_langcode = NULL, ?array $options = []): array {
    $destination_langcode = $entity->language()->getId();
    $target_langcode = $target_langcode ?? $destination_langcode;
    if ($entity instanceof TranslatableInterface && !$entity->hasTranslation($target_langcode)) {
      $target_langcode = $destination_langcode;
    }

    if (empty($this->recommendations[$entity->id()][$target_langcode][$limit])) {
      $data = [];

      // Get results from Elasticsearch.
      $query = $this->getElasticQuery($entity, $target_langcode, $limit, $options);
      $results = $query ? $this->searchElastic($query) : [];

      // Build result data.
      $data = $results ? $this->fetchNodeData($results, $target_langcode, $limit) : [];

      $this->recommendations[$entity->id()][$target_langcode][$limit] = $data;
    }

    return $this->recommendations[$entity->id()][$target_langcode][$limit];
  }

  /**
   * {@inheritDoc}
   */
  public function getCacheTagForUuid(string $uuid): string {
    return self::EXTERNAL_CACHE_TAG_PREFIX . $uuid;
  }

  /**
   * Get the cache tag for all recommendation blocks.
   *
   * @return string
   *   The cache tag.
   */
  public function getCacheTagForAll(): string {
    return self::EXTERNAL_CACHE_TAG_PREFIX . 'all';
  }

  /**
   * {@inheritDoc}
   */
  public function invalidateExternalCacheTags(array $uuids): void {
    $cache_tags = [];
    foreach ($uuids as $uuid) {
      $cache_tags[] = $this->getCacheTagForUuid($uuid);
    }
    $this->cacheTagInvalidator->invalidateTags($cache_tags);
  }

  /**
   * {@inheritDoc}
   */
  public function invalidateAllRecommendationBlocks(): void {
    $this->cacheTagInvalidator->invalidateTags([$this->getCacheTagForAll()]);
  }

  /**
   * {@inheritDoc}
   */
  public function getAllowedInstances(): array {
    $instances = [];

    $projects = $this->environmentResolver->getProjects();
    foreach ($projects as $key => $project) {
      if ($project->hasRole(ProjectRoleEnum::Core)) {
        $instances[$key] = $project->label();
      }
    }

    return $instances;
  }

  /**
   * {@inheritDoc}
   */
  public function getAllowedContentTypesAndBundles(): array {
    return [
      'node|news_article' => $this->t('News article', options: ['context' => 'helfi_recommendations']),
      'node|news_item' => $this->t('News item', options: ['context' => 'helfi_recommendations']),
      'node|page' => $this->t('Standard page', options: ['context' => 'helfi_recommendations']),
      'tpr_service|tpr_service' => $this->t('Service', options: ['context' => 'helfi_recommendations']),
    ];
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
   * Get valid instances.
   *
   * @param array $instances
   *   The instances to validate. Optional, defaults to all allowed instances.
   *
   * @return array
   *   The valid instances.
   */
  private function getValidInstances(array $instances = []): array {
    $allowed = array_keys($this->getAllowedInstances());

    if (empty($instances)) {
      return $allowed;
    }

    return array_intersect($instances, $allowed);
  }

  /**
   * Get valid content types and bundles.
   *
   * @param array $content_types_and_bundles
   *   The content types and bundles to validate. Optional, defaults to all
   *   allowed content types and bundles.
   *
   * @return array
   *   The valid content types and bundles.
   */
  private function getValidContentTypesAndBundles(array $content_types_and_bundles = []): array {
    $allowed = array_keys($this->getAllowedContentTypesAndBundles());

    if (empty($content_types_and_bundles)) {
      return $allowed;
    }

    return array_intersect($content_types_and_bundles, $allowed);
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
    // News content suggestions are always fetched from etusivu instance.
    if (in_array($entity->bundle(), ['news_item', 'news_article'])) {
      return [Project::ETUSIVU];
    }
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
    // News content suggestions will only show other news content types.
    if (in_array($entity->bundle(), ['news_item', 'news_article'])) {
      return ['node|news_article', 'node|news_item'];
    }
    return $this->getOptions($entity, 'content_types');
  }

  /**
   * Set query filter for instances.
   *
   * @param array &$query
   *   The query to set the filter for.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param array $options
   *   The options.
   */
  private function setQueryFilterForInstances(array &$query, ContentEntityInterface $entity, array $options): void {
    // First get allowed instances from provided options or the entity.
    $allowed_instances = $options['instances'] ?? $this->getEnabledInstances($entity);

    // Validate options against allowed instances.
    $allowed_instances = $this->getValidInstances($allowed_instances);

    // Set query filter for allowed instances.
    if (!empty($allowed_instances)) {
      $query[] = [
        'terms_set' => [
          'parent_instance' => [
            'terms' => $allowed_instances,
            'minimum_should_match_script' => [
              'source' => 'params["minimum_should_match"]',
              'params' => [
                'minimum_should_match' => 1,
              ],
            ],
          ],
        ],
      ];
    }
  }

  /**
   * Set query filter for content types and bundles.
   *
   * @param array &$query
   *   The query to set the filter for.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param array $options
   *   The options.
   */
  private function setQueryFilterForContentTypesAndBundles(array &$query, ContentEntityInterface $entity, array $options): void {
    $content_types = [];

    // First get allowed content types and bundles from provided options or
    // the entity.
    $allowed_content_types = $options['content_types'] ?? $this->getEnabledContentTypesAndBundles($entity);

    // Validate options against allowed content types and bundles.
    $allowed_content_types = $this->getValidContentTypesAndBundles($allowed_content_types);

    // Create an associative array of content types and bundles from the option
    // pair string format used in the options.
    foreach ($allowed_content_types as $option) {
      $option_pair = explode('|', $option);
      $entity_type = $option_pair[0];
      $bundle = $option_pair[1];

      if (!isset($content_types[$entity_type])) {
        $content_types[$entity_type] = [];
      }

      $content_types[$entity_type][] = $bundle;
    }

    // Set query filter for allowed content types.
    if (!empty($content_types)) {
      $allowed_entity_types = [];
      $allowed_bundles = [];

      foreach ($content_types as $content_type => $bundles) {
        $allowed_entity_types[] = $content_type;
        array_push($allowed_bundles, ...$bundles);
      }

      if ($allowed_entity_types) {
        $query[] = [
          'terms_set' => [
            'parent_type' => [
              'terms' => $allowed_entity_types,
              'minimum_should_match_script' => [
                'source' => 'params["minimum_should_match"]',
                'params' => [
                  'minimum_should_match' => 1,
                ],
              ],
            ],
          ],
        ];
      }

      // Set query filter for allowed bundles.
      if ($allowed_bundles) {
        $query[] = [
          'terms_set' => [
            'parent_bundle' => [
              'terms' => $allowed_bundles,
              'minimum_should_match_script' => [
                'source' => 'params["minimum_should_match"]',
                'params' => [
                  'minimum_should_match' => 1,
                ],
              ],
            ],
          ],
        ];
      }
    }
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
   * @param array $options
   *   Additional options to limit recommendations.
   *
   * @return array
   *   The elastic query.
   */
  private function getElasticQuery(ContentEntityInterface $entity, string $target_langcode, int $limit = 3, ?array $options = []): array {
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
            'bool' => [
              // Filter out entities that do not have a translation in the
              // target language.
              'must' => [
                [
                  'term' => [
                    'parent_translations' => $target_langcode,
                  ],
                ],
              ],
              // Filter out entities that have a published_at field and are
              // older than 365 days.
              'should' => [
                [
                  'range' => [
                    'parent_published_at' => [
                      'gte' => 'now-365d/d',
                    ],
                  ],
                ],
                [
                  'bool' => [
                    'must_not' => [
                      [
                        'exists' => [
                          'field' => 'parent_published_at',
                        ],
                      ],
                    ],
                  ],
                ],
              ],
              'minimum_should_match' => 1,
            ],
          ],
          'must' => [
            [
              'exists' => [
                'field' => 'parent_instance',
              ],
            ],
            [
              'exists' => [
                'field' => "parent_url_$target_langcode",
              ],
            ],
            [
              'exists' => [
                'field' => "parent_title_$target_langcode",
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

    // Filter instances, content types and bundles.
    $this->setQueryFilterForInstances($query['query']['bool']['filter']['bool']['must'], $entity, $options);
    $this->setQueryFilterForContentTypesAndBundles($query['query']['bool']['filter']['bool']['must'], $entity, $options);

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
      $uuid = !empty($hit['_source']['uuid']) ? reset($hit['_source']['uuid']) : NULL;
      $url = !empty($hit['_source']['parent_url_' . $target_langcode]) ? reset($hit['_source']['parent_url_' . $target_langcode]) : NULL;
      $title = !empty($hit['_source']['parent_title_' . $target_langcode]) ? reset($hit['_source']['parent_title_' . $target_langcode]) : NULL;
      $image_url = !empty($hit['_source']['parent_image_url']) ? reset($hit['_source']['parent_image_url']) : NULL;
      $image_alt = !empty($hit['_source']['parent_image_alt_' . $target_langcode]) ? reset($hit['_source']['parent_image_alt_' . $target_langcode]) : NULL;
      $published_at = !empty($hit['_source']['parent_published_at']) ? reset($hit['_source']['parent_published_at']) : NULL;
      $score = $hit['_score'] ?? NULL;

      // Check if all required fields are present.
      if (!$instance || !$uuid || !$url || !$title) {
        continue;
      }

      $data = [
        'uuid' => $uuid,
        'url' => $url,
        'title' => $title,
        'score' => $score,
      ];

      if ($image_url) {
        $image_url_absolute = str_starts_with($image_url, 'http://') || str_starts_with($image_url, 'https://');
        $theme = 'responsive_image';
        $image_uri = $image_url;

        // Use external image when the recommendation item is from a different
        // instance or the image is an absolute URL.
        if ($image_url_absolute || $instance !== $this->getParentInstance()) {
          $theme = 'imagecache_external_responsive';
          $environment = $this->environmentResolver->getEnvironment($instance, $this->environmentResolver->getActiveEnvironmentName());
          $image_uri = $image_url_absolute ? $image_url : sprintf('%s%s', $environment->getInternalBaseUrl(), $image_url);
        }

        $data['image'] = [
          '#theme' => $theme,
          '#uri' => $image_uri,
          '#responsive_image_style_id' => 'card_teaser',
          '#alt' => $image_alt,
          '#attributes' => [
            'alt' => $image_alt,
          ],
        ];
      }

      if ($published_at) {
        $data['published_at'] = $published_at;
      }

      $node_data[] = $data;

      if (count($node_data) >= $limit) {
        break;
      }
    }

    return $node_data;
  }

}
