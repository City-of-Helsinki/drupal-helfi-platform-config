<?php

namespace Drupal\helfi_calculator\Controller;

/**
 * @file
 * Contains \Drupal\helfi_calculator\Controller\ListController.
 */

use Drupal\user\Entity\User;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * List controller.
 */
class ListController {
  use StringTranslationTrait;

  /**
   * Controller to build hy admin tools list.
   *
   * @return array
   *   Render array.
   */
  public function build() {
    $current_user = User::load(\Drupal::currentUser()->id());
    $faked_blocks = [];

    if ($current_user->hasPermission('access administration pages')) {
      $faked_blocks['calculator_settings'] = [
        'title' => $this->t('Calculator settings'),
        'description' => $this->t('Edit calculator settings.'),
        'content' => [
          '#theme' => 'admin_block_content',
          '#content' => [
            'site_footer_translations' => [
              'url' => Url::fromRoute('helfi_calculator.calculator_settings_form'),
              'title' => $this->t('Edit calculator settings'),
              'description' => '',
              'options' => '',
            ],
          ],
        ],
      ];
    }

    return [
      '#theme' => 'admin_page',
      '#blocks' => $faked_blocks,
    ];
  }

}
