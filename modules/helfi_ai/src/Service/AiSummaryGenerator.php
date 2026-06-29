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
 * Generates AI-powered content summaries using the configured chat provider.
 */
class AiSummaryGenerator {

  public function __construct(
    private readonly AiProviderPluginManager $aiProvider,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TextConverterManager $textConverterManager,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Generates an HTML bullet-list summary for the given entity translation.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to summarise. May be unsaved (e.g. built from the current
   *   edit form via ContentEntityForm::buildEntity()); callers should set
   *   $entity->in_preview = TRUE so the renderer uses the in-memory (unsaved)
   *   values instead of cached or saved content.
   * @param string $langcode
   *   Language code of the translation to summarise.
   *
   * @return string|null
   *   The summary as a `<ul><li>` HTML string, or NULL on failure.
   */
  public function generate(ContentEntityInterface $entity, string $langcode): ?string {
    // Summarise the requested translation rather than the default one.
    if ($entity->hasTranslation($langcode)) {
      $entity = $entity->getTranslation($langcode);
    }

    // Render the entity to plain text. With no content there is nothing to
    // summarise.
    $content = $this->textConverterManager->convert($entity);
    if (!$content) {
      $this->logger->warning('helfi_ai: no text content for entity @type/@id.', [
        '@type' => $entity->getEntityTypeId(),
        '@id' => $entity->id(),
      ]);
      return NULL;
    }

    // Load the configured summary prompt template.
    /** @var \Drupal\ai\Entity\AiPromptInterface|null $prompt */
    $prompt = $this->entityTypeManager
      ->getStorage('ai_prompt')
      ->load('helfi_content_summary__helfi_content_summary_default');

    if (!$prompt instanceof AiPromptInterface) {
      $this->logger->error('helfi_ai: prompt helfi_content_summary__helfi_content_summary_default not found.');
      return NULL;
    }

    // Fill the prompt's {content} and {language} placeholders.
    $text = str_replace(
      ['{content}', '{language}'],
      [$content, $entity->language()->getName()],
      $prompt->getPrompt(),
    );

    try {
      // Send the prompt to the configured chat provider and turn its reply
      // into the HTML bullet list. A failed call (provider error, timeout)
      // returns NULL so the caller can show an error.
      ['provider_id' => $provider, 'model_id' => $model] = $this->aiProvider->getSetProvider('chat');
      $input = new ChatInput([new ChatMessage('user', $text)]);
      $normalized = $provider->chat($input, $model)->getNormalized();
      // A non-streaming chat reply is a single ChatMessage, not a stream.
      assert($normalized instanceof ChatMessage);
      $plain = $normalized->getText();
      return self::toHtmlBulletList($plain);
    }
    catch (\Throwable $e) {
      $this->logger->error('helfi_ai: generation failed: @message', ['@message' => $e->getMessage()]);
      return NULL;
    }
  }

  /**
   * Converts the plain-text bullet response into a `<ul><li>` list.
   */
  private static function toHtmlBulletList(string $plain): string {
    // Treat each non-empty line of the reply as one bullet.
    $lines = array_filter(
      array_map('trim', explode("\n", $plain)),
      fn(string $line) => $line !== '',
    );
    if (empty($lines)) {
      return '';
    }
    // Escape each line so the model output is treated as plain text, never as
    // markup, then wrap the lines as list items.
    $items = array_map(
      fn(string $line) => '<li>' . htmlspecialchars($line, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</li>',
      $lines,
    );
    return '<ul>' . implode('', $items) . '</ul>';
  }

}
