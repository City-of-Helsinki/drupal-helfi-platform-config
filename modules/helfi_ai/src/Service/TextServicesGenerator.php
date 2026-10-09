<?php

declare(strict_types=1);

namespace Drupal\helfi_ai\Service;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Utility\Error;
use Drupal\helfi_ai\TextServices\Service;
use Drupal\helfi_ai\TextServices\TextServicesClientInterface;
use Drupal\helfi_ai\TextServices\TextServicesException;
use Drupal\helfi_ai\TextServices\TextServicesRequest;
use Drupal\helfi_platform_config\TextConverter\TextConverterManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\ServerEvent;

/**
 * Generates AI suggestions with Helfi Text Services.
 */
class TextServicesGenerator {

  /**
   * Maximum content length in characters accepted by the API.
   */
  public const int MAX_CONTENT_LENGTH = 30000;

  /**
   * JSON schema for responses that are a list of text items.
   */
  private const array ITEMS_SCHEMA = [
    'type' => 'object',
    'properties' => [
      'items' => ['type' => 'array', 'items' => ['type' => 'string']],
    ],
    'required' => ['items'],
    'additionalProperties' => FALSE,
  ];

  public function __construct(
    private readonly TextServicesClientInterface $client,
    private readonly TextConverterManager $textConverterManager,
    private readonly RendererInterface $renderer,
    #[Autowire(service: 'logger.channel.helfi_ai')] private readonly LoggerInterface $logger,
  ) {
  }

  /**
   * Streams a tone-conforming rewrite of the content as server-sent events.
   *
   * Each event is JSON: {"delta": "..."} for each part of the rewrite as it
   * arrives, then {"done": true, "result": "<full rewrite>"}, or
   * {"error": true} if the rewrite fails.
   *
   * @param string $content
   *   The editor content (HTML).
   * @param string $langcode
   *   The language of the content.
   *
   * @return \Generator<int, \Symfony\Component\HttpFoundation\ServerEvent>
   *   The events.
   */
  public function streamTone(string $content, string $langcode): \Generator {
    $result = '';

    try {
      foreach ($this->client->stream(new TextServicesRequest(Service::Tone, $content, $langcode)) as $event) {
        if ($event['event'] !== 'delta') {
          continue;
        }
        $delta = (string) ($event['data']['text'] ?? '');

        if ($delta === '') {
          continue;
        }
        $result .= $delta;
        yield $this->serverEvent(['delta' => $delta]);
      }

      if (trim($result) === '') {
        throw new TextServicesException('Empty rewrite from Helfi Text Services');
      }
      yield $this->serverEvent(['done' => TRUE, 'result' => $result]);
    }
    catch (TextServicesException $e) {
      Error::logException($this->logger, $e);
      yield $this->serverEvent(['error' => TRUE]);
    }
  }

  /**
   * Returns the text of the given entity to summarize.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity. May be unsaved.
   *
   * @return string|null
   *   The text, cut to the maximum length, or NULL if the entity has no
   *   content.
   */
  public function summaryInput(ContentEntityInterface $entity): ?string {
    $content = trim($this->textConverterManager->convert($entity) ?? '');
    return $content === '' ? NULL : mb_substr($content, 0, self::MAX_CONTENT_LENGTH);
  }

  /**
   * Streams a summary of the given text as server-sent events.
   *
   * Each event is JSON: {"items": [...]} whenever another summary item is
   * complete, then {"done": true, "result": "<ul>...</ul>"}, or
   * {"error": true} if the summary fails.
   *
   * @param string $text
   *   The text to summarize.
   * @param string $langcode
   *   The language of the text.
   *
   * @return \Generator<int, \Symfony\Component\HttpFoundation\ServerEvent>
   *   The events.
   */
  public function streamSummary(string $text, string $langcode): \Generator {
    $json = '';
    $count = 0;

    try {
      foreach ($this->client->stream(new TextServicesRequest(Service::Summarize, $text, $langcode, self::ITEMS_SCHEMA)) as $event) {
        if ($event['event'] !== 'delta') {
          continue;
        }
        $json .= (string) ($event['data']['text'] ?? '');
        $items = $this->completedItems($json);

        if (count($items) > $count) {
          $count = count($items);
          yield $this->serverEvent(['items' => $items]);
        }
      }
      $items = $this->decodeItems($json);

      if (!$items) {
        throw new TextServicesException('Empty summary from Helfi Text Services');
      }
      yield $this->serverEvent(['done' => TRUE, 'result' => $this->renderItems($items)]);
    }
    catch (TextServicesException $e) {
      Error::logException($this->logger, $e);
      yield $this->serverEvent(['error' => TRUE]);
    }
  }

  /**
   * Suggests titles for the given entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity. May be unsaved.
   *
   * @return string[]
   *   Up to three titles, or an empty array if none were generated.
   */
  public function suggestTitles(ContentEntityInterface $entity): array {
    // @todo Use the SEO title service when it is published. Until then, the
    //   summary items are used as title suggestions.
    $text = $this->summaryInput($entity);

    if ($text === NULL) {
      return [];
    }

    try {
      $response = $this->client->send(new TextServicesRequest(Service::Summarize, $text, $entity->language()->getId(), self::ITEMS_SCHEMA));
      return array_slice($this->decodeItems($response->text), 0, 3);
    }
    catch (TextServicesException $e) {
      Error::logException($this->logger, $e);
      return [];
    }
  }

  /**
   * Decodes the summary items from a complete JSON response text.
   *
   * @param string $json
   *   The response text.
   *
   * @return string[]
   *   The items.
   *
   * @throws \Drupal\helfi_ai\TextServices\TextServicesException
   *   When the text is not a valid list of items.
   */
  private function decodeItems(string $json): array {
    $items = json_decode($json, TRUE)['items'] ?? NULL;

    if (!is_array($items) || !array_is_list($items) || array_filter($items, 'is_string') !== $items) {
      throw new TextServicesException('Invalid summary items from Helfi Text Services');
    }
    return $items;
  }

  /**
   * Returns the items that are complete in a partially received JSON text.
   *
   * An item is complete once its closing quote has arrived.
   *
   * @param string $json
   *   The JSON received so far, e.g. '{"items": ["First", "Sec'.
   *
   * @return string[]
   *   The complete items.
   */
  private function completedItems(string $json): array {
    $start = strpos($json, '[');

    if ($start === FALSE) {
      return [];
    }
    preg_match_all('/"((?:[^"\\\\]|\\\\.)*)"/', substr($json, $start), $matches);

    return array_values(array_filter(
      array_map(static fn (string $item) => json_decode('"' . $item . '"'), $matches[1]),
      'is_string',
    ));
  }

  /**
   * Renders the summary items as an HTML list.
   *
   * @param string[] $items
   *   The items.
   *
   * @return string
   *   The HTML list.
   */
  private function renderItems(array $items): string {
    $build = [
      '#theme' => 'item_list',
      '#items' => $items,
    ];
    return (string) $this->renderer->renderInIsolation($build);
  }

  /**
   * Creates a server-sent event carrying the given data as JSON.
   *
   * @param array<string, mixed> $data
   *   The data.
   *
   * @return \Symfony\Component\HttpFoundation\ServerEvent
   *   The event.
   */
  private function serverEvent(array $data): ServerEvent {
    return new ServerEvent(json_encode($data, JSON_THROW_ON_ERROR));
  }

}
