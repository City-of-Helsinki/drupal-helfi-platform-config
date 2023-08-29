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
      'langcode' => $langcode,
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

    $count = $json['count'];
    $results = $count > 3 ? array_slice($json['results'], 0, 3) : $json['results'];

    if (isset($parameters['ids']) && $parameters['ids']) {
      $items = array_filter($results, function ($item) use ($parameters) {
        return in_array($item['id'], $parameters['ids']);
      });
      $results = $items;
    }

    $data = [];

    foreach ($results as $hearing) {
      $existingTranslations = $this->getTranslationLanguages($hearing);
      $selectedLangcode = $this->resolveLanguage($hearing, $langcode);

      $item = [
        'id' => $hearing['id'],
        'open_at' => $hearing['open_at'],
        'created_at' => $hearing['created_at'],
        'close_at' => $hearing['close_at'],
        'n_comments' => $hearing['n_comments'],
        'slug' => $hearing['slug'],
        'organization' => $hearing['organization'],
        'main_image_url' => $hearing['main_image']['url'],
        'main_image' => Url::fromUri($hearing['main_image']['url']),
        'count' => $count,
        'url' => sprintf('%s%s', self::$hearingUrl, $hearing['slug']),
        'langcode' => $selectedLangcode,
        'existing_translations' => implode(',', $existingTranslations),
      ];

      $item['title'] = $hearing['title'][$selectedLangcode] ?? $hearing['title']['fi'];
      $item['abstract'] = $hearing['abstract'][$selectedLangcode] ?? $hearing['abstract']['fi'];
      $item['main_image_caption'] = $hearing['main_image']['caption'][$selectedLangcode] ?? $hearing['main_image']['caption']['fi'];

      $data[] = $item;
    }

    return $data;
  }

  /**
   * Get language that exists on a hearing, preferably current language.
   *
   * @param array $hearing
   *   The hearing.
   * @param string $currentLangCode
   *   Requested language code.
   *
   * @return string|void
   *   Language code that can be used to show the hearing.
   */
  private function resolveLanguage(array $hearing, string $currentLangCode): string {
    $existingTranslations = $this->getTranslationLanguages($hearing);
    if (in_array($currentLangCode, $existingTranslations)) {
      return $currentLangCode;
    }

    $possibleLanguages = ['fi', 'en', 'sv'];
    foreach ($possibleLanguages as $langcode) {
      if (in_array($langcode, $existingTranslations)) {
        return $langcode;
      }
    }
  }

  /**
   * Get all translations for hearing.
   *
   * @param array $hearing
   *   The hearing.
   *
   * @return string[]
   *   Translation language codes.
   */
  private static function getTranslationLanguages(array $hearing): array {
    return array_keys($hearing['title']);
  }

}
