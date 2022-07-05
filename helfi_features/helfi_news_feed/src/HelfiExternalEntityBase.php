<?php

namespace Drupal\helfi_news_feed;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\external_entities\ExternalEntityInterface;
use Drupal\external_entities\ExternalEntityStorage;
use Drupal\external_entities\StorageClient\ExternalEntityStorageClientBase;
use Drupal\helfi_api_base\Environment\Environment;
use Drupal\helfi_api_base\Environment\Project;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class used by taxonomy external entity.
 */
abstract class HelfiExternalEntityBase extends ExternalEntityStorageClientBase {

  /**
   * The active endpoint environment.
   *
   * @var \Drupal\helfi_api_base\Environment\Environment
   */
  protected Environment $environment;

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
   * {@inheritDoc}
   */
  public function loadMultiple(array $ids = NULL) {
    foreach ($ids ?? [] as $index => $id) {
      $this->query[sprintf('filter[id][value][%d]', $index)] = $id;
    }

    $data = $this->request($this->endpoint, $this->query);
    $prepared = [];
    foreach ($data as $value) {
      $prepared[$value["id"]] = $value;
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

    foreach ($parameters as $param) {
      ['field' => $field, 'value' => $values, 'operator' => $operator] = $param;
      if ($field == 'id') {
        $storage = \Drupal::entityTypeManager()->getStorage($this->getPluginId());
        $data = $storage->loadMultiple($values);
        $prepared = [];
        foreach ($data as $value) {
          $prepared[$value->id()] = ['id' => $value->id(), 'title' =>  $value->title->value];
        }
      }
      else {
        $this->query['filter[name-filter][condition][path]'] = 'name';
        $this->query['filter[name-filter][condition][value]'] = $values;
        $this->query['filter[name-filter][condition][operator]'] = $operator;
        $data = $this->request($this->endpoint, $this->query);
        $prepared = [];
        foreach ($data as $value) {
          $prepared[$value["id"]] = $value;
        }
      }
    }

    return $prepared;
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
   * Creates a request against JSON:API.
   *
   * @param string $endpoint
   *   Endpoint to send the request to.
   * @param array $parameters
   *   The query parameters.
   *
   * @return array
   *   An array of entities.
   */
  protected function request(
    string $endpoint,
    array $parameters,
  ) : array {
    try {
      $langcode = $this->languageManager->getCurrentLanguage()->getId();
      $uri = vsprintf('%s%s?%s', [
        $this->environment->getUrl($langcode),
        $endpoint,
        \GuzzleHttp\http_build_query($parameters),
      ]);

      $content = $this->client->request('GET', $uri);
      $json = \GuzzleHttp\json_decode($content->getBody()->getContents(), TRUE);
      return $json['data'];
    }
    catch (RequestException | GuzzleException $e) {
      watchdog_exception('helfi_external_entity', $e);
    }
    return [];
  }

}
