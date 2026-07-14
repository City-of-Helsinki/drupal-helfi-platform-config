<?php

declare(strict_types=1);

namespace Drupal\helfi_ai\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefinition;
use Drupal\Core\Access\CsrfRequestHeaderAccessCheck;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\editor\EditorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the runtime configuration for the tone-check CKEditor 5 plugin.
 *
 * Injects the endpoint URL, a CSRF request-header token, and the content
 * language into the editor so the JavaScript plugin can call the tone-check
 * route. The token matches what the route's _csrf_request_header_token check
 * expects.
 */
final class AiToneCheck extends CKEditor5PluginDefault implements ContainerFactoryPluginInterface {

  /**
   * Constructs a aiToneCheck plugin.
   *
   * @param array<string, mixed> $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param \Drupal\ckeditor5\Plugin\CKEditor5PluginDefinition $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Access\CsrfTokenGenerator $csrfToken
   *   The CSRF token generator.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    CKEditor5PluginDefinition $plugin_definition,
    private readonly CsrfTokenGenerator $csrfToken,
    private readonly LanguageManagerInterface $languageManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, mixed> $configuration
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('csrf_token'),
      $container->get('language_manager'),
    );
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $static_plugin_config
   *   The static plugin configuration.
   * @param \Drupal\editor\EditorInterface $editor
   *   The editor the plugin is attached to.
   *
   * @return array<string, mixed>
   *   The dynamic plugin configuration.
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    // These are per-request values. Computing them here is safe because a text
    // editor's JS settings are not cacheable (like forms) — the same reason
    // core injects CSRF tokens in dynamic config.
    // @see \Drupal\ckeditor5\Plugin\CKEditor5Plugin\DynamicPluginConfigWithCsrfTokenUrlTrait
    $static_plugin_config['aiToneCheck'] = [
      'endpoint' => Url::fromRoute('helfi_ai.tone_check')->toString(TRUE)->getGeneratedUrl(),
      'csrfToken' => $this->csrfToken->get(CsrfRequestHeaderAccessCheck::TOKEN_KEY),
      // Use the content language, not the UI language, so the tone prompt is
      // read in the language the content is authored in.
      'langcode' => $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId(),
    ];
    return $static_plugin_config;
  }

}
