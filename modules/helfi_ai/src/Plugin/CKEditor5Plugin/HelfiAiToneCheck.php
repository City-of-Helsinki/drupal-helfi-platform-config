<?php

declare(strict_types=1);

namespace Drupal\helfi_ai\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefinition;
use Drupal\Core\Access\CsrfRequestHeaderAccessCheck;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\editor\EditorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the runtime configuration for the tone-check CKEditor 5 plugin.
 *
 * Injects the endpoint URL, a CSRF request-header token, and the current
 * language into the editor so the JavaScript plugin can call the tone-check
 * route. The token matches what the route's _csrf_request_header_token check
 * expects.
 */
final class HelfiAiToneCheck extends CKEditor5PluginDefault implements ContainerFactoryPluginInterface {

  /**
   * Constructs a HelfiAiToneCheck plugin.
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
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   * @param array<string, mixed> $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param \Drupal\ckeditor5\Plugin\CKEditor5PluginDefinition $plugin_definition
   *   The plugin definition.
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
    $static_plugin_config['helfiAiToneCheck'] = [
      'endpoint' => Url::fromRoute('helfi_ai.tone_check')->toString(TRUE)->getGeneratedUrl(),
      'csrfToken' => $this->csrfToken->get(CsrfRequestHeaderAccessCheck::TOKEN_KEY),
      'langcode' => $this->languageManager->getCurrentLanguage()->getId(),
    ];
    return $static_plugin_config;
  }

}
