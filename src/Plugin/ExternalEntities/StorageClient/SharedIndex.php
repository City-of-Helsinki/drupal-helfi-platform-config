<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Plugin\ExternalEntities\StorageClient;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Utility\Error;
use Drupal\external_entities\Entity\ExternalEntityInterface;
use Drupal\external_entities\StorageClient\StorageClientBase;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_platform_config\MultisiteContentId;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ElasticsearchException;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Elastic\Transport\Exception\TransportException;
use Http\Promise\Promise;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * External entity storage client for shared index.
 *
 * @StorageClient(
 *   id = "helfi_shared_index",
 *   label = @Translation("Helfi: Shared frontpage index (embeddings)"),
 *   description = @Translation("Retrieves content from shared frontpage index (embeddings)")
 * )
 */
final class SharedIndex extends StorageClientBase {

  /**
   * Elasticsearch index name.
   */
  private const string INDEX = 'embeddings';

  /**
   * Elasticsearch client built by ClientBuilder.
   */
  protected Client $elasticsearchClient;

  /**
   * The environment resolver.
   */
  protected EnvironmentResolverInterface $environmentResolver;

  /**
   * The language manager.
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, mixed> $configuration
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) : self {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->elasticsearchClient = $container->get('helfi_platform_config.etusivu_elastic_client');
    $instance->environmentResolver = $container->get('helfi_api_base.environment_resolver');
    $instance->languageManager = $container->get('language_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function save(ExternalEntityInterface $entity) : int {
    return 1;
  }

  /**
   * {@inheritdoc}
   */
  public function delete(ExternalEntityInterface $entity) : void {
  }

  /**
   * Executes a search against the embeddings index.
   *
   * @param array<string, mixed> $body
   *   Elasticsearch request body.
   *
   * @return array<string, mixed>
   *   Raw Elasticsearch hits.
   */
  private function search(array $body): array {
    try {
      $response = $this->elasticsearchClient->search([
        'index' => self::INDEX,
        'body' => $body,
      ]);
      if ($response instanceof Promise) {
        $response = $response->wait();
      }
      if ($response instanceof Elasticsearch) {
        return $response->asArray()['hits']['hits'] ?? [];
      }
    }
    catch (ElasticsearchException | TransportException $e) {
      Error::logException($this->logger, $e);
    }

    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function ping() : bool {
    try {
      $response = $this->elasticsearchClient->indices()->exists([
        'index' => self::INDEX,
      ]);
      if ($response instanceof Promise) {
        $response = $response->wait();
      }
      return $response instanceof Elasticsearch && $response->getStatusCode() === 200;
    }
    catch (ElasticsearchException | TransportException) {
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string>|null $ids
   * @phpstan-return array<string, mixed>
   */
  public function loadMultiple(?array $ids = NULL) : array {
    if ($ids === NULL || $ids === []) {
      return [];
    }

    $hits = $this->querySource([
      [
        'field' => '_id',
        'operator' => 'IN',
        'value' => array_values($ids),
      ],
    ], [], NULL, count($ids));

    return array_column($hits, NULL, '_id');
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, mixed> $form
   * @phpstan-return array<string, mixed>
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    return [];
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<mixed> $parameters
   * @phpstan-param array<mixed> $sorts
   * @phpstan-return array<string, mixed>
   */
  public function querySource(
    array $parameters = [],
    array $sorts = [],
    ?int $start = NULL,
    ?int $length = NULL,
  ): array {
    $must = [];
    $filter = [];
    $must_not = [];

    foreach ($parameters as $parameter) {
      $field = $parameter['field'] ?? '';
      $value = $parameter['value'] ?? NULL;

      if ($field === 'search' && is_string($value) && $value !== '') {
        // Search by shared-index document id. This use case is reached when
        // an existing field is selected in the edit form, and will then
        // display just one suggestion result matching the internal uri.
        $id = MultisiteContentId::extractFromUserInput($value);
        if ($id !== NULL) {
          $filter[] = [
            'terms' => [
              '_id' => [$id],
            ],
          ];
          continue;
        }

        // A regular text search on label, metatag_title, and url fields.
        // This use case is reached when user starts typing in the link field.
        $must[] = [
          'multi_match' => [
            'query' => $value,
            'type' => 'best_fields',
            'fields' => [
              'label',
              'metatag_title',
              'url',
            ],
          ],
        ];

        // Exclude content from current instance and language, as they are
        // already suggested by the regular content entity matcher.
        $exclusion = $this->getCurrentInstanceCurrentLanguageExclusion();
        if ($exclusion !== NULL) {
          $must_not[] = $exclusion;
        }
      }
      elseif ($field === '_id' && $value !== NULL && $value !== []) {
        // Search by shared-index document id. This use case is reached when
        // Drupal loads the entity for rendering etc.
        $filter[] = [
          'terms' => [
            '_id' => array_map(
              strval(...),
              array_values((array) $value),
            ),
          ],
        ];
      }
    }

    // Filter by known instances to exclude content indexed
    // via crawling etc.
    $known_instances = $this->getKnownInstanceFilter();
    if ($known_instances !== NULL) {
      $filter[] = $known_instances;
    }

    $query = ['match_all' => new \stdClass()];
    if ($must !== [] || $filter !== [] || $must_not !== []) {
      $query = [
        'bool' => array_filter([
          'must' => $must,
          'filter' => $filter,
          'must_not' => $must_not,
        ]),
      ];
    }

    $body = [
      'size' => $length ?? 10,
      'query' => $query,
    ];
    if ($start !== NULL) {
      $body['from'] = $start;
    }

    return $this->search($body);
  }

  /**
   * Builds a filter limiting hits to instances known by EnvironmentResolver.
   *
   * @return array<string, mixed>|null
   *   Elasticsearch terms clause, or NULL if no projects are defined.
   */
  private function getKnownInstanceFilter(): ?array {
    $instances = array_keys($this->environmentResolver->getProjects());
    if ($instances === []) {
      return NULL;
    }

    return [
      'terms' => [
        'instance' => $instances,
      ],
    ];
  }

  /**
   * Builds a clause matching current instance content in the current language.
   *
   * Used to exclude those hits from autocomplete, while still allowing other
   * instances and other languages of the current instance.
   *
   * @return array<string, mixed>|null
   *   Elasticsearch bool clause, or NULL if the active project is unknown.
   */
  private function getCurrentInstanceCurrentLanguageExclusion(): ?array {
    try {
      $instance = $this->environmentResolver->getActiveProject()->getName();
    }
    catch (\InvalidArgumentException) {
      return NULL;
    }

    $langcode = $this->languageManager
      ->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)
      ->getId();

    return [
      'bool' => [
        'must' => [
          [
            'term' => [
              'instance' => $instance,
            ],
          ],
          [
            'term' => [
              'search_api_language' => $langcode,
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<mixed> $parameters
   * @phpstan-param array<mixed> $context
   * @phpstan-return array<string, mixed>
   */
  public function transliterateDrupalFilters(
    array $parameters,
    array $context = [],
  ): array {
    $source = [];

    foreach ($parameters as $parameter) {
      if (!is_array($parameter) || !isset($parameter['field'])) {
        continue;
      }

      $field = $parameter['field'];
      $operator = strtoupper((string) ($parameter['operator'] ?? '='));
      $value = $parameter['value'] ?? NULL;

      if (in_array($field, ['title', 'name', 'label'], TRUE) && is_string($value)) {
        if ($operator === 'LIKE') {
          $value = stripslashes(trim($value, '%'));
        }
        $source[] = [
          'field' => 'search',
          'operator' => 'CONTAINS',
          'value' => $value,
        ];
        continue;
      }

      if (in_array($field, ['id', 'uuid'], TRUE)) {
        $source[] = [
          'field' => '_id',
          'operator' => in_array($operator, ['IN', '='], TRUE) ? $operator : 'IN',
          'value' => (array) $value,
        ];
      }
    }

    return $this->transliterateDrupalFiltersAlter(
      [
        'source' => $source,
        'drupal' => [],
        'unhandled' => [],
      ],
      $parameters,
      $context
    );
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, mixed> $sorts
   * @phpstan-param array<string, mixed> $context
   * @phpstan-return array<string, mixed>
   */
  public function transliterateDrupalSorts(
    array $sorts,
    array $context = [],
  ): array {
    // Keep ranking on Elasticsearch (_score). Sending Drupal label sorts to
    // the Drupal side would force loading all hits before paging.
    return $this->transliterateDrupalSortsAlter(
      [
        'source' => [],
        'drupal' => [],
        'unhandled' => [],
      ],
      $sorts,
      $context
    );
  }

}
