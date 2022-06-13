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
 * External entity storage client for News tags taxonomy terms.
 *
 * @ExternalEntityStorageClient(
 *   id = "helfi_news_tags",
 *   label = @Translation("Helfi: News tags"),
 *   description = @Translation("Retrieves news tags taxonomy terms from Helfi")
 * )
 */
final class NewsTags extends ExternalEntityStorageClientBase {

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
  public function loadMultiple(array $ids = NULL) {
    $query = [];

    $language = $this->languageManager
      ->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)
      ->getId();

    foreach ($ids ?? [] as $index => $id) {
      $query[sprintf('filter[id][value][%d]', $index)] = $id;
    }

    $data = $this->request($query, $language);
    $prepared = [];
    foreach ($data as $key => $value) {
      $prepared[$value['id']] = $value;
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
   * {@inheritdoc}
   */
  public function query(
    array $parameters = [],
    array $sorts = [],
          $start = NULL,
          $length = NULL
  ) : array {
    $query = [];
    $query['filter[langcode]'] = 'fi';

    $data = $this->request($query, $query['filter[langcode]']);

    $prepared = [];
    foreach ($data as $key => $value) {
      $prepared[$value["id"]] = $value;
    }

    return $prepared;
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
      $uri = vsprintf('%s/jsonapi/taxonomy_term/news_group?%s', [
        $this->environment->getUrl($langcode),
        \GuzzleHttp\http_build_query($parameters),
      ]);
      $uri = 'https://nginx-etusivu-test.agw.arodevtest.hel.fi/fi/test-etusivu/jsonapi/taxonomy_term/news_group?filter%5Blangcode%5D=fi';
      $content = $this->client->request('GET', $uri);

      $json = \GuzzleHttp\json_decode($content->getBody()->getContents(), TRUE);

      return $json['data'];
    }
    catch (RequestException | GuzzleException $e) {
      watchdog_exception('helfi_news_groups', $e);
    }
    return [];
  }

}
