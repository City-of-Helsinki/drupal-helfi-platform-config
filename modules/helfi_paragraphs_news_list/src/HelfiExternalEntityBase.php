<?php

namespace Drupal\helfi_paragraphs_news_list;

use Drupal\Core\Config\ConfigFactoryInterface;
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
use GuzzleHttp\Utils;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class used by taxonomy external entity.
 */
abstract class HelfiExternalEntityBase extends ExternalEntityStorageClientBase {

  /**
   * The current language service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * The HTTP client service.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected ClientInterface $client;

  /**
   * Jsonapi query parameters.
   *
   * @var array
   */
  protected array $query;

  /**
   * Which endpoint to query.
   *
   * @var string
   */
  protected string $endpoint;

  /**
   * The active endpoint environment.
   *
   * @var \Drupal\helfi_api_base\Environment\Environment|null
   */
  private ?Environment $environment = NULL;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

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
    $instance->configFactory = $container->get('config.factory');
    /** @var \Drupal\helfi_api_base\Environment\EnvironmentResolver $environmentResolver */
    $environmentResolver = $container->get('helfi_api_base.environment_resolver');

    try {
      $instance->environment = $environmentResolver
        ->getEnvironment(Project::ETUSIVU, $environmentResolver->getActiveEnvironmentName());
    }
    catch (\InvalidArgumentException) {
    }
    $instance->logger = $container->get('logger.factory')->get('helfi_external_entity');

    return $instance;
  }

  /**
   * Prepares the query parameter for request.
   *
   * @param string $field
   *   The field.
   * @param int|string $value
   *   The value.
   *
   * @return string
   *   The prepared query parameter.
   */
  protected function prepareQueryParameter(string $field, int|string $value) : string {
    if ($field === 'id') {
      [$id] = explode(':', $value);

      return $id;
    }
    return $value;
  }

  /**
   * Generates a unique id for given item.
   *
   * @param array $data
   *   The data to generate id for.
   * @param string $langcode
   *   The fallback langcode.
   *
   * @return string
   *   A unique id.
   */
  protected function getUniqueId(array $data, string $langcode) : string {
    if (isset($data['attributes']['langcode'])) {
      $langcode = $data['attributes']['langcode'];
    }
    return sprintf('%s:%s', $data['id'], $langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(array $ids = NULL) {
    $this->query['filter[id][condition][path]'] = 'id';
    $this->query['filter[id][condition][operator]'] = 'IN';

    foreach ($ids ?? [] as $index => $id) {
      $this->query[sprintf('filter[id][condition][value][%d]', $index)] = $this
        ->prepareQueryParameter('id', $id);
    }

    $data = $this->request($this->query);

    $prepared = [];
    foreach ($data as $value) {
      $prepared[$value['id']] = $value;
    }
    return $prepared;
  }

  /**
   * {@inheritDoc}
   */
  public function query(
    array $parameters = [],
    array $sorts = [],
    $start = NULL,
    $length = NULL
  ) : array {
    $prepared = [];

    foreach ($parameters as $param) {
      ['field' => $field, 'value' => $values, 'operator' => $operator] = $param;

      if ($field === 'title') {
        $field = 'name';
      }

      if (is_array($values)) {
        $index = 0;
        foreach ($values as $value) {
          $prepared[sprintf('filter[%s-filter][condition][value][%d]', $field, $index++)] = $this
            ->prepareQueryParameter($field, $value);
        }
      }
      else {
        $prepared[sprintf('filter[%s-filter][condition][value]', $field)] = $this
          ->prepareQueryParameter($field, $values);
      }
      $prepared[sprintf('filter[%s-filter][condition][path]', $field)] = $field;
      $prepared[sprintf('filter[%s-filter][condition][operator]', $field)] = $operator;
    }

    return $this->request($prepared);
  }

  /**
   * {@inheritdoc}
   */
  public function save(ExternalEntityInterface $entity) : int {
    throw new EntityStorageException('::save() is not supported.');
  }

  /**
   * {@inheritdoc}
   */
  public function delete(ExternalEntityInterface $entity) : void {
    throw new EntityStorageException('::delete() is not supported.');
  }

  /**
   * Formats the JSON response.
   *
   * @param array $json
   *   The response JSON.
   * @param string $langcode
   *   The fallback langcode.
   *
   * @return array
   *   The data.
   */
  protected function formatResponse(array $json, string $langcode) : array {
    return array_map(function (array $item) use ($json, $langcode) : array {
      // Resolve and place all relationship data under corresponding parent
      // entity.
      if (isset($json['included'])) {
        $this->resolveRelationShip($item, $json['included']);
      }
      // Suffix all IDs with a language code to make sure they are cached
      // per language.
      if (isset($item['id'])) {
        $item['id'] = $this->getUniqueId($item, $langcode);
      }
      return $item;
    }, $json['data']);
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
  private function resolveRelationShip(array &$entity, array $includes) : void {
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

  /**
   * Creates a request against JSON:API.
   *
   * @param array $parameters
   *   The query parameters.
   *
   * @return array
   *   An array of entities.
   */
  protected function request(
    array $parameters,
  ) : array {
    if (!$this->environment) {
      return [];
    }
    try {
      $langcode = $this->languageManager
        ->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)
        ->getId();
      $uri = vsprintf('%s%s?%s', [
        $this->environment->getInternalAddress($langcode),
        $this->endpoint,
        \GuzzleHttp\http_build_query($parameters),
      ]);

      $content = $this->client->request('GET', $uri, [
        'curl' => [CURLOPT_TCP_KEEPALIVE => TRUE],
      ]);
      $json = Utils::jsonDecode($content->getBody()->getContents(), TRUE);
      return $this->formatResponse($json, $langcode);
    }
    catch (RequestException | GuzzleException $e) {
      watchdog_exception('helfi_external_entity', $e);
    }
    return [];
  }

}
