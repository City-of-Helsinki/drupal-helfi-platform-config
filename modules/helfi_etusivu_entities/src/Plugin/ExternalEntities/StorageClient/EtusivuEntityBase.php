<?php

declare(strict_types=1);

namespace Drupal\helfi_etusivu_entities\Plugin\ExternalEntities\StorageClient;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Utility\Error;
use Drupal\external_entities\ExternalEntityInterface;
use Drupal\external_entities\StorageClient\ExternalEntityStorageClientBase;
use Drupal\helfi_api_base\Environment\Environment;
use Drupal\helfi_api_base\Environment\Project;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Query;
use GuzzleHttp\Utils;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for etusivu external entity storage client.
 */
abstract class EtusivuEntityBase extends ExternalEntityStorageClientBase implements LoggerAwareInterface {

  use LoggerAwareTrait;

  /**
   * Custom cache tag.
   *
   * @var string
   */
  public static string $customCacheTag = 'helfi_external_entity';

  /**
   * The active endpoint environment.
   *
   * @var \Drupal\helfi_api_base\Environment\Environment|null
   */
  private ?Environment $environment = NULL;

  /**
   * The HTTP client service.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  private ClientInterface $client;

  /**
   * The current language service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) : self {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->client = $container->get('http_client');
    $instance->languageManager = $container->get('language_manager');

    /** @var \Drupal\helfi_api_base\Environment\EnvironmentResolver $environmentResolver */
    $environmentResolver = $container->get('helfi_api_base.environment_resolver');

    try {
      $instance->environment = $environmentResolver
        ->getEnvironment(Project::ETUSIVU, $environmentResolver->getActiveEnvironmentName());
    }
    catch (\InvalidArgumentException) {
    }
    $instance->setLogger($container->get('logger.channel.helfi_etusivu_entities'));

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
   * Creates a request against JSON:API.
   *
   * @param string $endpoint
   *   The jsonapi endpoint.
   * @param array $parameters
   *   The query parameters.
   * @param string $langcode
   *   The langcode.
   *
   * @return array
   *   An array of entities.
   */
  protected function request(string $endpoint, array $parameters, string $langcode) : array {
    if (!$this->environment) {
      return [];
    }
    try {
      $uri = vsprintf('%s/jsonapi%s?%s', [
        $this->environment->getInternalAddress($langcode),
        $endpoint,
        Query::build($parameters),
      ]);
      $content = $this->client->request('GET', $uri);
      $json = Utils::jsonDecode($content->getBody()->getContents(), TRUE);
      return $json['data'];
    }
    catch (RequestException | GuzzleException $e) {
      Error::logException($this->logger, $e);
    }
    return [];
  }

}
