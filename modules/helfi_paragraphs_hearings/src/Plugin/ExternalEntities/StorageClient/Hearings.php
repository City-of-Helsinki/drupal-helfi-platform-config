<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_hearings\Plugin\ExternalEntities\StorageClient;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use Drupal\external_entities\ExternalEntityInterface;
use Drupal\external_entities\StorageClient\ExternalEntityStorageClientBase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\InvalidArgumentException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Utils;
use Psr\Log\LoggerInterface;
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

  public const API_URL = 'https://kerrokantasi.api.hel.fi/v1/hearing?';

  public const HEARING_URL = 'https://kerrokantasi.hel.fi/';

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
    $plugin_definition,
  ) : self {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->languageManager = $container->get('language_manager');
    $instance->client = $container->get('http_client');
    $instance->logger = $container->get('logger.channel.helfi_platform_config');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(?array $ids = NULL) : array {
    return $this->query(['ids' => $ids]);
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
    $length = NULL,
  ) : array {

    $langcode = $this->languageManager
      ->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)
      ->getId();

    $query = http_build_query([
      'format' => 'json',
      'langcode' => $langcode,
      'open' => 'true',
      'limit' => 3,
    ]);

    $uri = sprintf('%s%s', self::API_URL, $query);

    try {
      $content = $this->client->request('GET', $uri);
      $json = Utils::jsonDecode($content->getBody()->getContents(), TRUE);
      if (empty($json['results'])) {
        return [];
      }
    }
    catch (RequestException | GuzzleException | InvalidArgumentException $e) {
      $this->logger->error('Hearings request failed with error: ' . $e->getMessage());
      return [];
    }

    $results = isset($parameters['ids']) ?
      array_filter($json['results'], fn ($item) => in_array($item['id'], $parameters['ids'])) :
      $json['results'];

    $data = [];

    foreach ($results as $hearing) {
      $existingTranslations = $this->getTranslationLanguages($hearing);
      $selectedLangcode = $this->resolveLanguage($hearing, $langcode);

      if (!in_array($langcode, $existingTranslations)) {
        continue;
      }

      $item = $hearing;
      $item += [
        'main_image_url' => $hearing['main_image']['url'],
        'main_image' => Url::fromUri($hearing['main_image']['url']),
        'count' => $json['count'],
        'url' => sprintf('%s%s', self::HEARING_URL, $hearing['slug']),
        'langcode' => $selectedLangcode,
        'existing_translations' => implode(',', $existingTranslations),
      ];

      $item['title'] = $hearing['title'][$selectedLangcode] ?? $hearing['title']['fi'] ?? '';
      $item['abstract'] = $hearing['abstract'][$selectedLangcode] ?? $hearing['abstract']['fi'] ?? '';
      $item['main_image_caption'] = $hearing['main_image']['caption'][$selectedLangcode]
        ?? $hearing['main_image']['caption']['fi']
        ?? '';

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
   * @return string
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
    throw new \InvalidArgumentException('Failed to resolve language.');
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
