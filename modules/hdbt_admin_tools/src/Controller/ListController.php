<?php

declare(strict_types=1);

namespace Drupal\hdbt_admin_tools\Controller;

/**
 * @file
 * Contains \Drupal\hdbt_admin_tools\Controller\ListController.
 */

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\user\Entity\User;

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
  public function build(): array {
    $current_user = User::load(\Drupal::currentUser()->id());
    $faked_blocks = [];

    if ($current_user->hasPermission('access administration pages')) {
      $faked_blocks['site_settings'] = [
        'title' => $this->t('Site settings'),
        'description' => $this->t('Edit site settings.'),
        'content' => [
          '#theme' => 'admin_block_content',
          '#content' => [
            'site_footer_translations' => [
              'url' => Url::fromRoute('hdbt_admin_tools.site_settings_form'),
              'title' => $this->t('Edit site settings'),
              'description' => '',
              'options' => '',
            ],
          ],
        ],
      ];
    }

    if ($current_user->hasPermission('administer menu')) {
      $faked_blocks['main_menu'] = [
        'title' => $this->t('Menus'),
        'description' => '',
        'content' => [
          '#theme' => 'admin_block_content',
          '#content' => [
            'navigation' => [
              'url' => Url::fromUri('internal:/admin/structure/menu/manage/main'),
              'title' => $this->t('Edit main menu'),
              'description' => '',
              'options' => '',
            ],
          ],
        ],
      ];
    }

    if (!\Drupal::moduleHandler()->moduleExists('helfi_navigation')) {
      $helfi_navigation = [
        'content' => [
          '#content' => [
            'navigation_footer_top' => [
              'url' => Url::fromUri('internal:/admin/structure/menu/manage/footer-top-navigation'),
              'title' => $this->t('Edit footer top navigation links'),
              'description' => $this->t('These links appear on top part of the footer.'),
              'options' => '',
            ],
            'navigation_footer_bottom' => [
              'url' => Url::fromUri('internal:/admin/structure/menu/manage/footer-bottom-navigation'),
              'title' => $this->t('Edit footer bottom navigation links'),
              'description' => $this->t('These links appear next to footer logo.'),
              'options' => '',
            ],
          ],
        ],
      ];
      $faked_blocks = array_merge_recursive($faked_blocks, $helfi_navigation);
    }

    if ($current_user->hasPermission('access taxonomy overview')) {
      $faked_blocks['taxonomy'] = [
        'title' => $this->t('Taxonomy'),
        'description' => '',
        'content' => [
          '#theme' => 'admin_block_content',
          '#content' => [
            'navigation' => [
              'url' => Url::fromUri('internal:/admin/structure/taxonomy'),
              'title' => $this->t('Edit taxonomy terms'),
              'description' => '',
              'options' => '',
            ],
          ],
        ],
      ];
    }

    if ($current_user->hasPermission('access administration pages')) {
      $faked_blocks['user_interface_translations'] = [
        'title' => $this->t('User interface translations'),
        'description' => '',
        'content' => [
          '#theme' => 'admin_block_content',
          '#content' => [
            'navigation' => [
              'url' => Url::fromUri('internal:/admin/config/regional/translate'),
              'title' => $this->t('Edit user interface translations'),
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
