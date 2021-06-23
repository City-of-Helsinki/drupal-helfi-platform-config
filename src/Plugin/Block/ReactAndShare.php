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
    $library = ['helfi_platform_config/react_and_share'];
    $build = [];

    $build['react_and_share'] = [

      '#theme' => 'react_and_share',
      '#title' => t('React and Share'),
      '#attached' => [
        'library' => $library,
      ],
    ];

    return $build;
  }

}
