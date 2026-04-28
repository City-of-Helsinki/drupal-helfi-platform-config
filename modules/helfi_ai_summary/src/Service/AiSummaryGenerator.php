<?php

declare(strict_types=1);

namespace Drupal\helfi_ai_summary\Service;

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
final class AiSummaryGenerator {

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
   *   The entity. Must already be saved so the text converter can render
   *   the rendered output.
   * @param string $langcode
   *   Language code of the translation to summarise.
   *
   * @return string|null
   *   The summary as a `<ul><li>` HTML string, or NULL on failure.
   */
  public function generate(ContentEntityInterface $entity, string $langcode): ?string {
    if ($entity->hasTranslation($langcode)) {
      $entity = $entity->getTranslation($langcode);
    }

    $content = $this->textConverterManager->convert($entity);
    if (!$content) {
      $this->logger->warning('helfi_ai_summary: no text content for entity @type/@id.', [
        '@type' => $entity->getEntityTypeId(),
        '@id' => $entity->id(),
      ]);
      return NULL;
    }

    /** @var \Drupal\ai\Entity\AiPromptInterface|null $prompt */
    $prompt = $this->entityTypeManager
      ->getStorage('ai_prompt')
      ->load('helfi_content_summary__helfi_content_summary_default');

    if (!$prompt instanceof AiPromptInterface) {
      $this->logger->error('helfi_ai_summary: prompt helfi_content_summary__helfi_content_summary_default not found.');
      return NULL;
    }

    $text = str_replace(
      ['{content}', '{language}'],
      [$content, $entity->language()->getName()],
      $prompt->getPrompt(),
    );

    try {
      ['provider_id' => $provider, 'model_id' => $model] = $this->aiProvider->getSetProvider('chat');
      $input = new ChatInput([new ChatMessage('user', $text)]);
      $plain = $provider->chat($input, $model)->getNormalized()->getText();
      return self::toHtmlBulletList($plain);
    }
    catch (\Throwable $e) {
      $this->logger->error('helfi_ai_summary: generation failed: @message', ['@message' => $e->getMessage()]);
      return NULL;
    }
  }

  /**
   * Converts the plain-text bullet response into a `<ul><li>` list.
   */
  private static function toHtmlBulletList(string $plain): string {
    $lines = array_filter(
      array_map('trim', explode("\n", $plain)),
      fn(string $line) => $line !== '',
    );
    if (empty($lines)) {
      return '';
    }
    $items = array_map(
      fn(string $line) => '<li>' . htmlspecialchars($line, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</li>',
      $lines,
    );
    return '<ul>' . implode('', $items) . '</ul>';
  }

}
