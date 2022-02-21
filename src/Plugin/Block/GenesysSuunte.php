<?php

namespace Drupal\helfi_platform_config\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a Genesys Chat (SUUNTE) block.
 *
 * @Block(
 *  id = "genesys_suunte",
 *  admin_label = @Translation("Genesys Chat (SUUNTE)"),
 * )
 */
class GenesysSuunte extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $library = ['helfi_platform_config/genesys_suunte'];
    $build = [];

    $build['genesys_suunte'] = [
      '#title' => t('Genesys Chat (SUUNTE)'),
      '#attached' => [
        'library' => $library,
      ],
    ];

    return $build;
  }

}
