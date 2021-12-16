<?php

namespace Drupal\helfi_platform_config\Plugin\Block;

use Drupal\Core\Block\BlockBase;

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
   * {@inheritdoc}
   */
  public function build() {
    $language = \Drupal::languageManager()->getCurrentLanguage();
    $langcode = $language->getId();

    if (!$apikey = getenv('REACT_AND_SHARE_APIKEY_' . strtoupper($langcode))) {
      return [];
    }

    $libraries = [
      'helfi_platform_config/react_and_share',
      'helfi_platform_config/react_and_share_cookie_compliance',
    ];

    $build['react_and_share'] = [
      '#theme' => 'react_and_share',
      '#title' => t('React and Share'),
      '#attached' => [
        'library' => $libraries,
        'drupalSettings' => ['reactAndShareApiKey' => $apikey],
      ],
    ];

    return $build;
  }

}
