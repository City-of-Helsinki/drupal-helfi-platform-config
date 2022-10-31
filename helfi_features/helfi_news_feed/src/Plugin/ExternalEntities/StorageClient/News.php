<?php

declare(strict_types = 1);

namespace Drupal\helfi_news_feed\Plugin\ExternalEntities\StorageClient;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\external_entities\ExternalEntityInterface;
use Drupal\external_entities\StorageClient\ExternalEntityStorageClientBase;
use Drupal\helfi_api_base\Environment\Environment;
use Drupal\helfi_api_base\Environment\Project;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * External entity storage client for News feed entities.
 *
 * @ExternalEntityStorageClient(
 *   id = "helfi_news",
 *   label = @Translation("Helfi: News"),
 *   description = @Translation("Retrieves 'news' content from Helfi")
 * )
 */
final class News extends ExternalEntityStorageClientBase {

  /**
   * The active endpoint environment.
   *
   * @var \Drupal\helfi_api_base\Environment\Environment
   */
  private Environment $environment;

  /**
   * The current language service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  private LanguageManagerInterface $languageManager;

  /**
   * The HTTP client service.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  private ClientInterface $client;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) : self {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->languageManager = $container->get('language_manager');
    $instance->client = $container->get('http_client');

    $environment = $container->get('config.factory')
      ->get('helfi_news_feed.settings')
      ->get('source_environment') ?: 'prod';

    /** @var \Drupal\helfi_api_base\Environment\EnvironmentResolver $environmentResolver */
    $environmentResolver = $container->get('helfi_api_base.environment_resolver');
    $instance->environment = $environmentResolver
      ->getEnvironment(Project::ETUSIVU, $environment);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(array $ids = NULL) : array {
    $query = [
      'filter[id][operator]' => 'IN',
      // Include main image, tags, neighbourhoods and groups fields.
      'include' => 'main_image.media_image,tags,groups,neighbourhoods',
      'fields[file--file]' => 'uri,url',
    ];
    $language = $this->languageManager
      ->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)
      ->getId();

    foreach ($ids ?? [] as $index => $id) {
      $query[sprintf('filter[id][value][%d]', $index)] = $id;
    }
    $data = $this->request($query, $language);

    // The $ids are passed in correct order, but the external data is not
    // in same order. Sort data by given $ids.
    usort($data, function (array $a, array $b) use ($ids) {
      return array_search($a['id'], $ids) - array_search($b['id'], $ids);
    });

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function save(ExternalEntityInterface $entity) : void {
    throw new EntityStorageException('::save() is not supported.');
  }

  /**
   * {@inheritdoc}
   */
  public function delete(ExternalEntityInterface $entity) : void {
    throw new EntityStorageException('::delete() is not supported.');
  }

  /**
   * Creates a JSON:API filter for given term field.
   *
   * @param string $name
   *   The field name.
   * @param array $terms
   *   The terms.
   *
   * @return string[]
   *   The filter.
   */
  private function createTermFilter(string $name, array $terms) : array {
    if (!$terms) {
      return [];
    }
    $query = [
      sprintf('filter[taxonomy_term--news_%s][condition][operator]', $name) => 'IN',
    ];
    $query[sprintf('filter[taxonomy_term--news_%s][condition][path]', $name)] = $name === 'tags'
      ? "field_news_item_$name.id"
      : "field_news_$name.id";

    // Filter by multiple terms using 'OR' condition.
    foreach ($terms as $key => $value) {
      $query[sprintf('filter[taxonomy_term--news_%s][condition][value][%d]', $name, $key)] = $value['target_id'];
    }
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function query(
    array $parameters = [],
    array $sorts = [],
    $start = NULL,
    $length = NULL
  ) : array {
    $query = [
      // We only care about basic entity data here.
      'fields[node--news_item]' => 'id',
      // No need to fetch non-published entities.
      'fields[status]' => 1,
    ];

    if ($start) {
      $query['page[offset]'] = $start;
    }

    if ($length) {
      $query['page[limit]'] = $length;
    }

    // Map query fields to JSON:API fields.
    // @todo Document these fields.
    foreach ($parameters as $param) {
      ['field' => $field, 'value' => $value, 'operator' => $op] = $param;

      $match = match($field) {
        'langcode' => function (string $value, ?string $op): array {
          return ['filter[langcode]' => $value];
        },
        'tags' => function (array $terms, ?string $op): array {
          return $this->createTermFilter('tags', $terms);
        },
        'groups' => function (array $terms, ?string $op) : array {
          return $this->createTermFilter('groups', $terms);
        },
        'neighbourhoods' => function (array $terms, ?string $op) : array {
          return $this->createTermFilter('neighbourhoods', $terms);
        }
      };
      try {
        $query += $match($value, $op);
      }
      catch (\UnhandledMatchError) {
      }
    }

    // Map sort fields to JSON:API fields.
    // @todo Document these fields.
    foreach ($sorts as $sort) {
      ['field' => $field, 'direction' => $direction] = $sort;
      $match = match ($field) {
        'published_at' => function (string $direction) : array {
          return [
            'sort[published_at][path]' => 'published_at',
            'sort[published_at][direction]' => $direction,
          ];
        },
      };

      try {
        $query += $match($direction);
      }
      catch (\UnhandledMatchError) {
      }
    }

    // If filter is missing language set it manually.
    if (!isset($query['filter[langcode]'])) {
      $language = $this->languageManager
        ->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)
        ->getId();
      $query['filter[langcode]'] = $language;
    }
    return $this->request($query, $query['filter[langcode]']);
  }

  /**
   * Creates a request against JSON:API.
   *
   * @param array $parameters
   *   The query parameters.
   * @param string $langcode
   *   The langcode.
   *
   * @return array
   *   An array of entities.
   */
  private function request(array $parameters, string $langcode) : array {
    try {
      $uri = vsprintf('%s/jsonapi/node/news?%s', [
        $this->environment->getInternalAddress($langcode),
        \GuzzleHttp\http_build_query($parameters),
      ]);
      $content = $this->client->request('GET', $uri);

      $json = \GuzzleHttp\json_decode($content->getBody()->getContents(), TRUE);

      return array_map(function (array $item) use ($json) : array {
        // Resolve and place all relationship data under corresponding parent
        // entity.
        if (isset($json['included'])) {
          $this->resolveRelationShip($item, $json['included']);
        }
        return $item;
      }, $json['data']);
    }
    catch (RequestException | GuzzleException $e) {
      watchdog_exception('helfi_news_feed', $e);
    }
    return [];
  }

  /**
   * Gets the related entity.
   *
   * @param array $element
   *   The base jsonapi response.
   * @param string $id
   *   The id to find.
   *
   * @return array|null
   *   The related element.
   */
  private function resolveInclude(array $element, string $id) : ? array {
    $key = array_search($id, array_column($element, 'id'));

    if ($key === FALSE) {
      return NULL;
    }
    return $element[$key];
  }

  /**
   * Resolves the relationships for given item.
   *
   * @param array $entity
   *   The base jsonapi response.
   * @param array $includes
   *   The includes array.
   */
  private function resolveRelationShip(array &$entity, array $includes) {

    if (empty($entity['relationships'])) {
      $entity['relationships'] = [];
    }

    foreach ($entity['relationships'] as &$relationship) {
      if (empty($relationship['data'])) {
        continue;
      }
      $rdata = &$relationship['data'];

      // Handle elements without nested relationships.
      if (!isset($rdata[0])) {
        if (!$element = $this->resolveInclude($includes, $rdata['id'])) {
          continue;
        }
        $this->resolveRelationShip($element, $includes);

        $rdata = $element;

        continue;
      }
      foreach ($rdata as &$item) {
        if (!$element = $this->resolveInclude($includes, $item['id'])) {
          continue;
        }

        $item = $element;
        // Fetch nested relationships.
        $this->resolveRelationShip($element, $item);
      }
    }
  }

}
