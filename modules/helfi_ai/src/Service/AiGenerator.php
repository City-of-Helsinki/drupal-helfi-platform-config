<?php

declare(strict_types=1);

namespace Drupal\helfi_ai\Service;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Entity\AiPromptInterface;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Utility\Error;
use Drupal\helfi_platform_config\TextConverter\TextConverterManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Ai suggestions.
 */
final readonly class AiGenerator {

  public function __construct(
    #[Autowire(service: 'ai.provider')] private AiProviderPluginManager $aiProvider,
    private EntityTypeManagerInterface $entityTypeManager,
    private TextConverterManager $textConverterManager,
    private RendererInterface $renderer,
    #[Autowire(service: 'logger.channel.helfi_ai')] private LoggerInterface $logger,
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

    if (!$content || strlen($content) > 256 * 1024) {
      return [];
    }
    $message = $this
      ->getChatMessage($content, $entity->language()->getName(), 'helfi_seo_title__helfi_seo_title_default');

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

    if (!$content) {
      return NULL;
    }
    $message = $this
      ->getChatMessage($content, $entity->language()->getName(), 'helfi_content_summary__helfi_content_summary_default');

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
   * @param string $language
   *   The language name.
   * @param string $promptId
   *   The prompt id.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatMessage|null
   *   The chat message or NULL.
   */
  private function getChatMessage(string $content, string $language, string $promptId): ?ChatMessage {
    /** @var \Drupal\ai\Entity\AiPromptInterface|null $prompt */
    $prompt = $this->entityTypeManager
      ->getStorage('ai_prompt')
      ->load($promptId);

    if (!$prompt instanceof AiPromptInterface) {
      return NULL;
    }

    // Fill the prompt placeholders.
    $text = str_replace(
      ['{content}', '{language}'],
      [$content, $language],
      $prompt->getPrompt(),
    );

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

}
