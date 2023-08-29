<?php

declare(strict_types = 1);

namespace Drupal\helfi_paragraphs_hearings\Plugin\ExternalEntities\StorageClient;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use Drupal\external_entities\ExternalEntityInterface;
use Drupal\external_entities\StorageClient\ExternalEntityStorageClientBase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Utils;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * External entity storage client for hearings.
 *
 * @ExternalEntityStorageClient(
 *   id = "helfi_hearings",
 *   label = @Translation("Helfi: Hearings"),
 *   description = @Translation("Retrieves hearings from hearing api")
 * )
 */
final class Hearings extends ExternalEntityStorageClientBase {

  /**
   * Custom cache tag for hearings.
   *
   * @var string
   */
  public static string $customCacheTag = 'helfi_hearings';

  /**
   * Api base url.
   *
   * @var string
   */
  public static string $apiUrl = 'https://api.hel.fi/kerrokantasi/v1/hearing?';

  /**
   * Hearing base url.
   *
   * @var string
   */
  public static string $hearingUrl = 'https://kerrokantasi.hel.fi/';

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

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(array $ids = NULL) : array {
    $ids = $ids ?: [];

    $data = $this->query(['ids' => $ids]);

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
   * {@inheritdoc}
   */
  public function query(
    array $parameters = [],
    array $sorts = [],
          $start = NULL,
          $length = NULL
  ) : array {

    $langcode = $this->languageManager
      ->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)
      ->getId();

    $query = http_build_query([
      'format' => 'json',
      'langcode' => 'fi',
      'open' => 'true',
    ]);

    $uri = sprintf('%s%s', self::$apiUrl, $query);

    try {
      $content = $this->client->request('GET', $uri);
      $json = Utils::jsonDecode($content->getBody()->getContents(), TRUE);
      if (empty($json['results'])) {
        return [];
      }
    }
    catch (RequestException | GuzzleException $e) {
      watchdog_exception('helfi_paragraphs_hearings', $e);
    }

    $results = $json['results'];
    $count = $json['count'];
    if ($parameters['ids']) {
      $items = array_filter($json['results'], function ($item) use ($parameters) {
        return $item['id'] === $parameters['ids'][0];
      });

      $results = [reset($items)];
    }

    $data = [];

    foreach ($results as $hearing) {
      $item = [
        'id' => $hearing['id'],
        'open_at' => $hearing['open_at'],
        'created_at' => $hearing['created_at'],
        'close_at' => $hearing['close_at'],
        'n_comments' => $hearing['n_comments'],
        'slug' => $hearing['slug'],
        'organization' => $hearing['organization'],
        'main_image' => Url::fromUri($hearing['main_image']['url']),
        'count' => $count,
        'url' => sprintf('%s%s', self::$hearingUrl, $hearing['slug']),
      ];

      $item['title'] = $hearing['title'][$langcode] ?? $hearing['title']['fi'];
      $item['abstract'] = $hearing['abstract'][$langcode] ?? $hearing['abstract']['fi'];
      $item['main_image_caption'] = $hearing['main_image']['caption'][$langcode] ?? $hearing['main_image']['caption']['fi'];

      $data[] = $item;
    }

    return $data;
  }

}
