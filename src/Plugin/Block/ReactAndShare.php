<?php

namespace Drupal\helfi_platform_config\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Language\LanguageInterface;
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

  /**
   * Language manager.
   *
   * @var Drupal\Core\Language\LanguageManagerInterface
   */
  private LanguageManagerInterface $languageManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    // @todo Use dependency injection.
    // phpcs:ignore DrupalPractice.Objects.GlobalDrupal.GlobalDrupal
    $this->languageManager = \Drupal::languageManager();
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
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
        ],
      ],
    ];

    return $build;
  }

}
