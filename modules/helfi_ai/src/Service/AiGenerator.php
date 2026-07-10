<?php

declare(strict_types=1);

namespace Drupal\helfi_ai\Service;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Utility\Error;
use Drupal\helfi_platform_config\TextConverter\TextConverterManager;
use Drupal\language\ConfigurableLanguageManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Ai suggestions.
 */
final readonly class AiGenerator {

  /**
   * Maximum accepted content size in bytes (256 KB).
   *
   * Bounds the payload sent to the AI provider to avoid runaway cost/latency.
   */
  public const int MAX_CONTENT_BYTES = 256 * 1024;

  public function __construct(
    #[Autowire(service: 'ai.provider')] private AiProviderPluginManager $aiProvider,
    private TextConverterManager $textConverterManager,
    private RendererInterface $renderer,
    #[Autowire(service: 'logger.channel.helfi_ai')] private LoggerInterface $logger,
    private ConfigurableLanguageManagerInterface $languageManager,
  ) {}

  /**
   * Suggests title candidates for the given entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to title, in the language it should be titled in. May be
   *   unsaved.
   *
   * @return string[]
   *   Up to three title candidates, or an empty array on
   *   failure.
   */
  public function suggestTitles(ContentEntityInterface $entity): array {
    // Skip when the entity has no content.
    $content = $this->textConverterManager->convert($entity);

    if (!$content || strlen($content) > self::MAX_CONTENT_BYTES) {
      return [];
    }
    $message = $this
      ->getChatMessage($content, $entity->language()->getId(), 'helfi_seo_title__helfi_seo_title_default');

    if (!$message) {
      return [];
    }

    $titles = [];
    foreach (explode("\n", $message->getText()) as $line) {
      // Strip list markers then trim whitespace and quotes.
      $line = preg_replace('/^\s*(?:\d+[.)]|[-*•])\s*/u', '', $line) ?? $line;
      $line = trim($line, " \t\"'");
      if ($line !== '') {
        $titles[] = $line;
      }
    }
    return array_slice($titles, 0, 3);
  }

  /**
   * Suggests a tone-conforming rewrite of the given editor content.
   *
   * @param string $content
   *   The editor content (HTML) to check.
   * @param string $langcode
   *   Language of the content; selects the prompt translation to apply.
   *
   * @return string|null
   *   The rewritten content, or NULL when there is nothing to check or the
   *   request fails.
   */
  public function checkTone(string $content, string $langcode): ?string {
    $message = $this
      ->getChatMessage($content, $langcode, 'helfi_tone_check__helfi_tone_check_default');

    if (!$message) {
      return NULL;
    }
    return $message->getText() ?? NULL;
  }

  /**
   * Generates an AI summary for the given entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return string|null
   *   The summary or NULL.
   */
  public function generateSummary(ContentEntityInterface $entity): ?string {
    $content = $this->textConverterManager->convert($entity);

    $message = $this
      ->getChatMessage($content, $entity->language()->getId(), 'helfi_content_summary__helfi_content_summary_default');

    if (!$message) {
      return NULL;
    }

    // Treat each non-empty line of the reply as one bullet.
    $lines = array_filter(
      array_map('trim', explode("\n", $message->getText())),
      fn(string $line) => $line !== '',
    );
    if (empty($lines)) {
      return '';
    }

    $build = [
      '#theme' => 'item_list',
      '#list_type' => 'ul',
      '#items' => $lines,
    ];

    return (string) $this->renderer->renderInIsolation($build);
  }

  /**
   * An AI prompt for given text.
   *
   * @param string $content
   *   The content.
   * @param string $langcode
   *   The language name.
   * @param string $promptId
   *   The prompt id.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatMessage|null
   *   The chat message or NULL.
   */
  private function getChatMessage(string $content, string $langcode, string $promptId): ?ChatMessage {
    if (!$prompt = $this->loadPrompt($promptId, $langcode)) {
      return NULL;
    }
    $content = trim($content);

    // Fill the prompt placeholders.
    $text = str_replace('{content}', $content, $prompt);

    if ($text === '') {
      return NULL;
    }

    try {
      ['provider_id' => $provider, 'model_id' => $model] = $this->aiProvider->getSetProvider('chat');
      $input = new ChatInput([new ChatMessage('user', $text)]);
      $normalized = $provider->chat($input, $model)->getNormalized();
      // A non-streaming chat reply is a single ChatMessage, not a stream.
      assert($normalized instanceof ChatMessage);

      return $normalized;
    }
    catch (\Throwable $e) {
      Error::logException($this->logger, $e);
    }
    return NULL;
  }

  /**
   * Reads the prompt text for the given language.
   *
   * Switches the config override language so the language-specific translation
   * of the prompt is returned, falling back to the untranslated value when no
   * translation exists.
   *
   * @param string $promptId
   *   The prompt.
   * @param string $langcode
   *   The language to read the prompt in.
   *
   * @return string|null
   *   The prompt text, or NULL if it is missing or empty.
   */
  private function loadPrompt(string $promptId, string $langcode): ?string {
    $promptId = sprintf('ai.ai_prompt.%s', $promptId);

    $prompt = $this->languageManager
      ->getLanguageConfigOverride($langcode, $promptId)
      ->get('prompt');

    return (string) $prompt ?? NULL;
  }

}
