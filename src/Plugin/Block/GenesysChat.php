<?php

namespace Drupal\helfi_platform_config\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a Genesys Chat block.
 *
 * @Block(
 *  id = "genesys_chat",
 *  admin_label = @Translation("Genesys Chat"),
 * )
 */
class GenesysChat extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $library = ['helfi_platform_config/genesys_kymp'];
    $build = [];

    $build['genesys_chat'] = [
      '#title' => t('Genesys KYMP'),
      '#attached' => [
        'library' => $library,
      ],
    ];

    return $build;
  }

}
