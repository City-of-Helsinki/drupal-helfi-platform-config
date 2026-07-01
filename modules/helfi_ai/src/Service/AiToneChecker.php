<?php

declare(strict_types=1);

namespace Drupal\helfi_ai\Service;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\language\ConfigurableLanguageManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Rewrites editor content to conform to the standard tone of voice.
 *
 * Uses one prompt whose text is translated per language: the prompt is read in
 * the content's language so the language-specific tone guidance is applied.
 */
class AiToneChecker {

  /**
   * Config name of the tone-check prompt entity.
   */
  private const PROMPT = 'ai.ai_prompt.helfi_tone_check__helfi_tone_check_default';

  public function __construct(
    private readonly AiProviderPluginManager $aiProvider,
    private readonly ConfigurableLanguageManagerInterface $languageManager,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly LoggerInterface $logger,
  ) {}

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
  public function check(string $content, string $langcode): ?string {
    if (trim($content) === '') {
      return NULL;
    }

    // Read the prompt in the content's language so the translated tone guidance
    // is used regardless of the current UI language.
    $template = $this->loadPrompt($langcode);
    if ($template === NULL) {
      $this->logger->error('helfi_ai: tone check prompt @name not found.', ['@name' => self::PROMPT]);
      return NULL;
    }

    $text = str_replace('{content}', $content, $template);

    try {
      // Send the prompt to the configured chat provider and return its reply.
      // A failed call (provider error, timeout) returns NULL so the caller can
      // surface an error.
      ['provider_id' => $provider, 'model_id' => $model] = $this->aiProvider->getSetProvider('chat');
      $input = new ChatInput([new ChatMessage('user', $text)]);
      $normalized = $provider->chat($input, $model)->getNormalized();
      // A non-streaming chat reply is a single ChatMessage, not a stream.
      assert($normalized instanceof ChatMessage);
      $suggestion = trim($normalized->getText());
      return $suggestion === '' ? NULL : $suggestion;
    }
    catch (\Throwable $e) {
      $this->logger->error('helfi_ai: tone check failed: @message', ['@message' => $e->getMessage()]);
      return NULL;
    }
  }

  /**
   * Reads the prompt text for the given language.
   *
   * Switches the config override language so the language-specific translation
   * of the prompt is returned, falling back to the untranslated value when no
   * translation exists.
   *
   * @param string $langcode
   *   The language to read the prompt in.
   *
   * @return string|null
   *   The prompt text, or NULL if it is missing or empty.
   */
  private function loadPrompt(string $langcode): ?string {
    $language = $this->languageManager->getLanguage($langcode);
    if (!$language) {
      return NULL;
    }

    $original = $this->languageManager->getConfigOverrideLanguage();
    $this->languageManager->setConfigOverrideLanguage($language);
    try {
      $prompt = $this->configFactory->get(self::PROMPT)->get('prompt');
    }
    finally {
      $this->languageManager->setConfigOverrideLanguage($original);
    }

    return is_string($prompt) && $prompt !== '' ? $prompt : NULL;
  }

}
