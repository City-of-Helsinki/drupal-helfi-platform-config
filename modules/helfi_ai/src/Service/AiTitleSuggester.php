<?php

declare(strict_types=1);

namespace Drupal\helfi_ai\Service;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Entity\AiPromptInterface;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\helfi_platform_config\TextConverter\TextConverterManager;
use Psr\Log\LoggerInterface;

/**
 * Suggests GEO/SEO-optimized page titles using the configured chat provider.
 */
class AiTitleSuggester {

  /**
   * ID of the prompt entity that defines the SEO-title instructions.
   */
  private const PROMPT_ID = 'helfi_seo_title__helfi_seo_title_default';

  /**
   * Maximum number of suggestions returned, regardless of the model's reply.
   */
  private const MAX_SUGGESTIONS = 3;

  /**
   * Maximum content size in bytes sent to the provider.
   */
  private const MAX_CONTENT_BYTES = 256 * 1024;

  public function __construct(
    private readonly AiProviderPluginManager $aiProvider,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TextConverterManager $textConverterManager,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Suggests title candidates for the given entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to title, in the language it should be titled in. May be
   *   unsaved.
   *
   * @return string[]
   *   Up to self::MAX_SUGGESTIONS title candidates, or an empty array on
   *   failure.
   */
  public function suggest(ContentEntityInterface $entity): array {
    // Skip when the entity has no content.
    $content = $this->textConverterManager->convert($entity);
    if (!$content) {
      $this->logger->warning('helfi_ai: no text content for entity @type/@id.', [
        '@type' => $entity->getEntityTypeId(),
        '@id' => $entity->id(),
      ]);
      return [];
    }

    if (strlen($content) > self::MAX_CONTENT_BYTES) {
      $this->logger->warning('helfi_ai: content for entity @type/@id is too large (@bytes bytes) to suggest a title.', [
        '@type' => $entity->getEntityTypeId(),
        '@id' => $entity->id(),
        '@bytes' => strlen($content),
      ]);
      return [];
    }

    /** @var \Drupal\ai\Entity\AiPromptInterface|null $prompt */
    $prompt = $this->entityTypeManager
      ->getStorage('ai_prompt')
      ->load(self::PROMPT_ID);

    if (!$prompt instanceof AiPromptInterface) {
      $this->logger->error('helfi_ai: prompt @id not found.', ['@id' => self::PROMPT_ID]);
      return [];
    }

    $text = str_replace(
      ['{content}', '{language}'],
      [$content, $entity->language()->getName()],
      $prompt->getPrompt(),
    );

    try {
      ['provider_id' => $provider, 'model_id' => $model] = $this->aiProvider->getSetProvider('chat');
      $input = new ChatInput([new ChatMessage('user', $text)]);
      $normalized = $provider->chat($input, $model)->getNormalized();
      // A non-streaming chat reply is a single ChatMessage, not a stream.
      assert($normalized instanceof ChatMessage);
      return self::parseTitles($normalized->getText());
    }
    catch (\Throwable $e) {
      $this->logger->error('helfi_ai: title suggestion failed: @message', ['@message' => $e->getMessage()]);
      return [];
    }
  }

  /**
   * Parses the model reply into a clean list of title candidates.
   *
   * @param string $plain
   *   The raw chat reply.
   *
   * @return string[]
   *   Up to self::MAX_SUGGESTIONS cleaned titles.
   */
  private static function parseTitles(string $plain): array {
    $titles = [];
    foreach (explode("\n", $plain) as $line) {
      // Strip list markers then trim whitespace and quotes.
      $line = preg_replace('/^\s*(?:\d+[.)]|[-*•])\s*/u', '', $line) ?? $line;
      $line = trim($line, " \t\"'");
      if ($line !== '') {
        $titles[] = $line;
      }
    }
    return array_slice($titles, 0, self::MAX_SUGGESTIONS);
  }

}
