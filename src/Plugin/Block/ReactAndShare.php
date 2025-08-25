<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\language\ConfigurableLanguageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'ReactAndShare' block.
 */
#[Block(
  id: "react_and_share",
  admin_label: new TranslatableMarkup("React and Share"),
)]
final class ReactAndShare extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Language manager.
   *
   * @var \Drupal\language\ConfigurableLanguageManagerInterface
   */
  private ConfigurableLanguageManagerInterface $languageManager;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): self {
    $instance = new self($configuration, $plugin_id, $plugin_definition);
    assert($container->get('language_manager') instanceof ConfigurableLanguageManagerInterface);
    $instance->languageManager = $container->get('language_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $langcode = $this->languageManager
      ->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)
      ->getId();

    if (!$apikey = getenv('REACT_AND_SHARE_APIKEY_' . strtoupper($langcode))) {
      return [];
    }

    $library = ['helfi_platform_config/react_and_share'];
    $sitename = $this->languageManager
      ->getLanguageConfigOverride('fi', 'system.site')
      ->get('name');
    $askem_monitoring_enabled = \Drupal::state()->get('askem.script_monitoring', TRUE);

    $build['react_and_share'] = [
      '#theme' => 'react_and_share',
      '#title' => $this->t('React and Share'),
      '#attached' => [
        'library' => $library,
        'drupalSettings' => [
          'reactAndShareApiKey' => $apikey,
          'siteName' => $sitename,
          'askemMonitoringEnabled' => (bool) $askem_monitoring_enabled,
        ],
      ],
    ];

    return $build;
  }

}
