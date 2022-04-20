<?php

namespace Drupal\helfi_platform_config\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Provides a 'ReactAndShare' block.
 *
 * @Block(
 *  id = "react_and_share",
 *  admin_label = @Translation("React and Share"),
 * )
 */
class ReactAndShare extends BlockBase {

  private LanguageManagerInterface $languageManager;

  public function __construct(array $configuration, $plugin_id, $plugin_definition)
  {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->languageManager = \Drupal::languageManager();
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $langcode = $this->languageManager
      ->getCurrentLanguage()
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
      '#title' => t('React and Share'),
      '#attached' => [
        'library' => $library,
        'drupalSettings' => [
          'reactAndShareApiKey' => $apikey,
          'siteName' => $sitename,
        ],
      ],
    ];

    return $build;
  }

}
