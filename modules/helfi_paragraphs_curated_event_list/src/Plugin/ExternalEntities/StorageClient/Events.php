<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_curated_event_list\Plugin\ExternalEntities\StorageClient;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\external_entities\Entity\ExternalEntityInterface;
use Drupal\external_entities\StorageClient\StorageClientBase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Utils;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * External entity storage client for LinkedEvents events.
 *
 * @StorageClient(
 *   id = "linkedevents_events",
 *   label = @Translation("LinkedEvents: Events"),
 *   description = @Translation("Retrieves 'events' content from LinkedEvents")
 * )
 */
class Events extends StorageClientBase {
  protected const string API_URL = 'https://api.hel.fi/linkedevents/v1';
  protected const string EVENTS_BASE_URL = 'https://tapahtumat.hel.fi';

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
  protected ClientInterface $client;

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

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function save(ExternalEntityInterface $entity) : int {
    return 1;
  }

  /**
   * {@inheritdoc}
   */
  public function delete(ExternalEntityInterface $entity) : void {
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(?array $ids = NULL) : array {
    return $this->query(['ids' => $ids]);
  }

  /**
   * Gets the URI for given language and query parameters.
   *
   * @param array<mixed> $parameters
   *   The query parameters.
   * @param string $langcode
   *   The langcode.
   *
   * @return string
   *   The URI.
   */
  private function getUri(array $parameters, string $langcode): string {
    $uri = fn (string $endpoint, array $query) => sprintf('%s/%s?%s', self::API_URL, $endpoint, http_build_query($query));

    // Run when loading a list of entities
    // eg. when creating autocomplete optionlist.
    if (isset($parameters['ids'])) {
      $query = [
        // We add language code to the ID to make it unique. Strip the
        // language code when querying the API.
        'ids' => implode(',', array_map(fn(string $id) => explode(',', $id)[0], $parameters['ids'])),
        'language' => $langcode,
      ];

      return $uri('event', $query);
    }

    if (isset($parameters[0]['field']) && $parameters[0]['field'] === 'id') {
      $query = [
        'ids' => implode(',', $parameters[0]['value']),
        'language' => $langcode,
      ];
      return $uri('event', $query);
    }
    // Enable searching directly with an event id.
    if (preg_match('/.{0,15}:.{0,40}/i', $parameters[0]['value'])) {
      $query = [
        'ids' => $parameters[0]['value'],
        'language' => $langcode,
      ];
      return $uri('event', $query);
    }

    // Run when receiving input from autocomplete field.
    $query = [
      'input' => $parameters[0]['value'],
      'language' => $langcode,
      'start' => date('Y-m-d'),
      'type' => 'event',
    ];
    return $uri('search', $query);
  }

  /**
   * {@inheritdoc}
   */
  public function query(
    array $parameters = [],
    array $sorts = [],
    ?int $start = NULL,
    ?int $length = NULL,
    array &$unhandled_filters = [],
  ) : array {
    $langcode = $this->languageManager
      ->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)
      ->getId();

    try {
      $content = $this->client->request('GET', $this->getUri($parameters, $langcode));
      $json = Utils::jsonDecode($content->getBody()->getContents(), TRUE);

      if (empty($json['data'])) {
        return [];
      }
    }
    catch (\Exception) {
      return [];
    }

    $prepared = [];

    $paths = [
      'sv' => 'kurser',
      'en' => 'events',
      'fi' => 'tapahtumat',
    ];
    foreach ($json['data'] as $event) {
      if (!isset($event['name'][$langcode])) {
        continue;
      }
      $originalId = $event['id'];
      $start = new \DateTime($event['start_time']);

      $event['external_link'] = vsprintf('%s/%s/%s/%s', [
        self::EVENTS_BASE_URL,
        $langcode,
        $paths[$langcode],
        $originalId,
      ]);
      // Make sure event id is unique per language.
      $event['id'] = sprintf('%s,%s', $originalId, $langcode);
      $event['title'] = $event['name'][$langcode] . ' (' . $start->format('d.m.Y H:i') . ')';
      $event['name'] = $event['name'][$langcode];

      $prepared[$event['id']] = $event;
    }

    return $prepared;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function querySource(
    array $parameters = [],
    array $sorts = [],
    ?int $start = NULL,
    ?int $length = NULL,
  ): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function transliterateDrupalFilters(
    array $parameters,
    array $context = [],
  ): array {
    return [];
  }

}
