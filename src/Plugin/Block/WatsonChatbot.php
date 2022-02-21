<?php

namespace Drupal\helfi_platform_config\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a Watson chatbot block.
 *
 * @Block(
 *  id = "watson_chatbot",
 *  admin_label = @Translation("Watson chatbot"),
 * )
 */
class WatsonChatbot extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $library = ['helfi_platform_config/watson_chatbot'];
    $build = [];

    $build['watson_chatbot'] = [
      '#title' => t('Watson chatbot'),
      '#attached' => [
        'library' => $library,
      ],
    ];

    return $build;
  }

}
