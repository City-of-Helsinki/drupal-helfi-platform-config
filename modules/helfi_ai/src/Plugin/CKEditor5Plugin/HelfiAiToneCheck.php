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
