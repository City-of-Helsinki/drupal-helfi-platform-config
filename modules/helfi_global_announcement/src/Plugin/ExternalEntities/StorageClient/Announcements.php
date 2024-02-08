<?php

declare(strict_types = 1);

namespace Drupal\helfi_global_announcement\Plugin\ExternalEntities\StorageClient;

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
 * External entity storage client for News feed entities.
 *
 * @ExternalEntityStorageClient(
 *   id = "helfi_announcements",
 *   label = @Translation("Helfi: Announcements"),
 *   description = @Translation("Retrieves announcements from helfi")
 * )
 */
final class Announcements extends ExternalEntityStorageClientBase {

  /**
   * Custom cache tag for announcements.
   *
   * @var string
   */
  public static string $customCacheTag = 'helfi_external_entity_announcement';

  /**
   * The active endpoint environment.
   *
   * @var \Drupal\helfi_api_base\Environment\Environment|null
   */
  private ?Environment $environment = NULL;

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
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private LoggerInterface $logger;

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

    /** @var \Drupal\helfi_api_base\Environment\EnvironmentResolver $environmentResolver */
    $environmentResolver = $container->get('helfi_api_base.environment_resolver');

    try {
      $instance->environment = $environmentResolver
        ->getEnvironment(Project::ETUSIVU, $environmentResolver->getActiveEnvironmentName());
    }
    catch (\InvalidArgumentException) {
    }
    $instance->logger = $container->get('logger.factory')->get('helfi_announcements');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(array $ids = NULL) : array {
    $ids = $ids ?: [];

    $query = [
      'filter[id][operator]' => 'IN',
    ];

    foreach ($ids as $index => $id) {
      $query[sprintf('filter[id][value][%d]', $index)] = $id;
    }

    $data = $this->query($query);

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
   * {@inheritdoc}
   */
  public function query(
    array $parameters = [],
    array $sorts = [],
          $start = NULL,
          $length = NULL
  ) : array {
    $query = [
      'fields[node--announcements]' => 'id',
      'fields[status]' => 1,
      'filter[status][value]' => 1,
      'filter[field_publish_externally][value]' => 1,
    ];

    if ($start) {
      $query['page[offset]'] = $start;
    }

    if ($length) {
      $query['page[limit]'] = $length;
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
    if (!$this->environment) {
      return [];
    }
    try {
      $uri = vsprintf('%s/jsonapi/node/announcement?%s', [
        $this->environment->getInternalAddress($langcode),
        \GuzzleHttp\http_build_query($parameters),
      ]);
      $content = $this->client->request('GET', $uri);
      $json = Utils::jsonDecode($content->getBody()->getContents(), TRUE);
      return $json['data'];
    }
    catch (RequestException | GuzzleException $e) {
      watchdog_exception('helfi_announcements', $e);
    }
    return [];
  }

}
