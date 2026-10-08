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
   * Suggests a tone-conforming rewrite of the given editor content.
   *
   * @param string $content
   *   The editor content (HTML).
   * @param string $langcode
   *   The language of the content.
   *
   * @return string|null
   *   The rewritten content, or NULL if the request fails.
   */
  public function checkTone(string $content, string $langcode): ?string {
    try {
      return $this->client->send(new TextServicesRequest(Service::Tone, $content, $langcode))->text;
    }
    catch (TextServicesException $e) {
      Error::logException($this->logger, $e);
      return NULL;
    }
  }

  /**
   * Generates a summary of the given entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity. May be unsaved.
   *
   * @return string|null
   *   The summary as an HTML list, or NULL if no summary was generated.
   */
  public function generateSummary(ContentEntityInterface $entity): ?string {
    $items = $this->summarize($entity);

    if (!$items) {
      return NULL;
    }
    $build = [
      '#theme' => 'item_list',
      '#list_type' => 'ul',
      '#items' => $items,
    ];
    return (string) $this->renderer->renderInIsolation($build);
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
    return array_slice($this->summarize($entity), 0, 3);
  }

  /**
   * Summarizes the given entity into a list of items.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return string[]
   *   The summary items, or an empty array if the entity has no content or the
   *   request fails.
   */
  private function summarize(ContentEntityInterface $entity): array {
    $content = trim($this->textConverterManager->convert($entity) ?? '');

    if ($content === '') {
      return [];
    }

    try {
      $response = $this->client->send(new TextServicesRequest(
        Service::Summarize,
        mb_substr($content, 0, self::MAX_CONTENT_LENGTH),
        $entity->language()->getId(),
        self::ITEMS_SCHEMA,
      ));
      $items = json_decode($response->text, TRUE)['items'] ?? NULL;

      if (!is_array($items) || !array_is_list($items) || array_filter($items, 'is_string') !== $items) {
        throw new TextServicesException('Invalid summary items from Helfi Text Services');
      }
      return $items;
    }
    catch (TextServicesException $e) {
      Error::logException($this->logger, $e);
      return [];
    }
  }

}
