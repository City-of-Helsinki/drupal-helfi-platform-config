<?php

declare(strict_types = 1);

namespace Drupal\helfi_news_feed\Plugin\ExternalEntities\StorageClient;

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
      // Include extra data.
      'include' => 'main_image.media_image',
      'fields[file--file]' => 'uri,url',
    ];
    $language = $this->languageManager
      ->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)
      ->getId();

    foreach ($ids as $index => $id) {
      $query[sprintf('filter[id][value][%d]', $index)] = $id;
    }
    return $this->request($query, $language);
  }

  /**
   * {@inheritdoc}
   */
  public function save(ExternalEntityInterface $entity) : void {
    // Not supported.
  }

  /**
   * {@inheritdoc}
   */
  public function delete(ExternalEntityInterface $entity) : void {
    // Not supported.
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
    if ($start) {
      $query['page[offset]'] = $start;
    }

    if ($length) {
      $query['page[limit]'] = $length;
    }
    // We only care about basic entity data here.
    $query['fields[node--news_item]'] = 'id';

    foreach ($parameters as $param) {
      ['field' => $field, 'value' => $value, 'operator' => $op] = $param;

      $match = match($field) {
        'langcode' => function (string $value, ?string $op): array {
          return ['filter[langcode]' => $value];
        },
        'tags' => function (array $tags, ?string $op): array {
          $query = [];
          foreach ($tags as $key => $tag) {
            // Only show entities that contain ALL defined tags, like
            // (WHERE tag = 'first' AND tag = 'second').
            $query += [
              sprintf('filter[tags-%s-and][group][conjunction]', $key) => 'AND',
              sprintf('filter[tag-%s][condition][path]', $key) => 'news_item_tags.name',
              sprintf('filter[tag-%s][condition][value]', $key) => $tag,
              sprintf('filter[tag-%s][condition][memberOf]', $key) => sprintf('tags-%s-and', $key),
            ];
          }
          return $query;
        },
      };
      try {
        $query += $match($value, $op);
      }
      catch (\UnhandledMatchError) {
      }
    }

    if (!isset($query['filter[langcode]'])) {
      throw new \InvalidArgumentException('Missing required "langcode" filter.');
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
        $this->environment->getUrl($langcode),
        \GuzzleHttp\http_build_query($parameters),
      ]);
      $content = $this->client->request('GET', $uri);

      $json = \GuzzleHttp\json_decode($content->getBody()->getContents(), TRUE);

      return array_map(function (array $item) use ($json) : array {
        if (isset($json['included'])) {
          $this->resolveRelationShip($item, $json['included']);
        }
        return $item;
      }, $json['data']);
    }
    catch (RequestException | GuzzleException) {
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
