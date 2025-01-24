<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_curated_event_list\Plugin\ExternalEntities\StorageClient;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\external_entities\ExternalEntityInterface;
use Drupal\external_entities\StorageClient\ExternalEntityStorageClientBase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Utils;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Extrernal entity storage client for LinkedEvents events.
 *
 * @ExternalEntityStorageClient(
 *   id = "linkedevents_events",
 *   label = @Translation("LinkedEvents: Events"),
 *   description = @Translation("Retrieves 'events' content from LinkedEvents")
 * )
 */
class Events extends ExternalEntityStorageClientBase {
  protected const API_URL = 'https://api.hel.fi/linkedevents/v1';
  protected const EVENTS_BASE_URL = 'https://tapahtumat.hel.fi/';

  /**
   * The current language service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  private LanguageManagerInterface $languageManager;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

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
    $instance->logger = $container->get('logger.factory')->get('helfi_external_entity');

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

    // Run when loading a list of entities
    // eg. when creating autocomplete optionlist.
    if (isset($parameters['ids'])) {
      $endpoint = 'event';
      $query = http_build_query([
        'ids' => implode(',', $parameters['ids']),
        'language' => $langcode,
      ]);
    }
    elseif (
      isset($parameters[0]['field']) &&
      $parameters[0]['field'] === 'id'
    ) {
      $endpoint = 'event';
      $query = http_build_query([
        'ids' => implode(',', $parameters[0]['value']),
        'language' => $langcode,
      ]);
    }
    // Enable searching directly with an event id.
    elseif (preg_match('/.{0,15}:.{0,40}/i', $parameters[0]['value'])) {
      $endpoint = 'event';
      $query = http_build_query(
        [
          'ids' => $parameters[0]['value'],
          'language' => $langcode,
        ]
      );
    }
    // Run when receiving input from autocomplete field.
    else {
      $endpoint = 'search';
      $query = http_build_query([
        'input' => $parameters[0]['value'],
        'language' => $langcode,
        'start' => date('Y-m-d'),
        'type' => 'event',
      ]);
    }

    $uri = sprintf('%s/%s?%s', self::API_URL, $endpoint, $query);

    try {
      $content = $this->client->request('GET', $uri);
      $json = Utils::jsonDecode($content->getBody()->getContents(), TRUE);

      if (empty($json['data'])) {
        return [];
      }
    }
    catch (\Throwable $t) {
      $this->logger->error(
        'Linked Events external entity request failed with error: ' .
        $t->getMessage()
      );

      return [];
    }

    $prepared = [];
    foreach ($json['data'] as $event) {
      if (!isset($event['name'][$langcode])) {
        $this->logger->error(
          'Event with id: ' . $event['id'] . ' has no name in language: ' . $langcode
        );

        continue;
      }

      $event_url = '/events/';
      if ($langcode === 'fi') {
        $event_url = '/tapahtumat/';
      } elseif ($langcode === 'sv') {
        $event_url = '/kurser/';
      }

      $event['clean_title'] = $event['name'][$langcode];
      $start = new \DateTime($event['start_time']);
      $event['title'] = $event['clean_title'] . ' (' . $start->format('d.m.Y H:i') . ')';
      $event['external_link'] = self::EVENTS_BASE_URL . $langcode . $event_url . $event['id'];
      $prepared[$event['id']] = $event;
    }

    return $prepared;
  }

}
