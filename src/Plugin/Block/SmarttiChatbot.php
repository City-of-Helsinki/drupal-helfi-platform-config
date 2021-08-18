<?php

namespace Drupal\helfi_platform_config\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'SmarttiChatbot' block.
 *
 * @Block(
 *  id = "smartti_chatbot",
 *  admin_label = @Translation("Smartti Chatbot"),
 * )
 */
class SmarttiChatbot extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $library = ['helfi_platform_config/smartti_chatbot'];
    $build = [];

    $build['smartti_chatbot'] = [
      '#title' => t('Smartti Chatbot'),
      '#attached' => [
        'library' => $library,
      ],
    ];

    return $build;
  }

}
