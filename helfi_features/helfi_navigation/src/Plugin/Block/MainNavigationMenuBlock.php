<?php

declare(strict_types=1);

namespace Drupal\helfi_navigation\Plugin\Block;

use Drupal\Core\Language\LanguageInterface;

/**
 * Provides an external menu block for global main navigation..
 *
 * @Block(
 *   id = "external_menu_block_main_navigation",
 *   admin_label = @Translation("External menu block - Main global navigation"),
 *   category = @Translation("External menu"),
 * )
 */
final class MainNavigationMenuBlock extends ExternalMenuBlockBase {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeId() : string {
    return 'main';
  }

  /**
   * {@inheritdoc}
   */
  protected function buildMenuTree(): array {
    try {
      $data = $this->apiManager->getMainMenu(
        $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId()
      );

      $menu = [];
      foreach ($data as $item) {
        if (!isset($item->menu_tree)) {
          continue;
        }
        $menu[] = reset($item->menu_tree);
      }

      return $menu;
    }
    catch (\Exception $e) {
    }
    return [];
  }

}
