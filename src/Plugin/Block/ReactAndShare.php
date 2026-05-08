<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\State\StateInterface;
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

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly ConfigurableLanguageManagerInterface $languageManager,
    private readonly StateInterface $state,
    private readonly RouteMatchInterface $routeMatch,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $languageManager = $container->get('language_manager');
    assert($languageManager instanceof ConfigurableLanguageManagerInterface);
    $state = $container->get('state');
    assert($state instanceof StateInterface);
    $routeMatch = $container->get('current_route_match');
    assert($routeMatch instanceof RouteMatchInterface);
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $languageManager,
      $state,
      $routeMatch,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    if ($this->routeMatch->getRouteName() === 'entity.user.canonical') {
      return [];
    }

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

    $build['react_and_share'] = [
      '#theme' => 'react_and_share',
      '#title' => $this->t('React and Share'),
      '#attached' => [
        'library' => $library,
        'drupalSettings' => [
          'reactAndShareApiKey' => $apikey,
          'siteName' => $sitename,
          'askemMonitoringEnabled' => (bool) $this->state->get('askem.script_monitoring', TRUE),
        ],
      ],
    ];

    return $build;
  }

}
