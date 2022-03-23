<?php

namespace Drupal\helfi_platform_config\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a Genesys Chat (Neuvonta) block.
 *
 * @Block(
 *  id = "genesys_neuvonta",
 *  admin_label = @Translation("Genesys Chat (Neuvonta)"),
 * )
 */
class GenesysNeuvonta extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $library = ['helfi_platform_config/genesys_neuvonta'];
    $build = [];

    $build['genesys_neuvonta'] = [
      '#title' => t('Genesys Chat (Neuvonta)'),
      '#attached' => [
        'library' => $library,
      ],
    ];

    return $build;
  }
}
