<?php

namespace Drupal\helfi_platform_config\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'KuuraHealthChat' block.
 *
 * @Block(
 *  id = "kuura_health_chat",
 *  admin_label = @Translation("Kuura Health Chat"),
 * )
 */
class KuuraHealthChat extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $library = ['helfi_platform_config/kuura_health_chat'];
    $build = [];

    $build['kuura_health_chat'] = [
      '#title' => t('Kuura Health Chat'),
      '#attached' => [
        'library' => $library,
      ],
    ];

    return $build;
  }

}
