<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation\Plugin\Block;

use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_api_base\Menu\Menu;

/**
 * Provides an external menu block.
 *
 * @Block(
 *   id = "external_menu_block",
 *   admin_label = @Translation("External menu block"),
 *   category = @Translation("External menu")
 * )
 */
class ExternalMenuBlock extends ExternalMenuBlockBase {

  public function getData(): string {
    // @todo Retrieve menu type from block settings.
    $menu_type = Menu::MAIN_MENU;

    return $this->globalNavigationService->makeRequest(
      Project::ETUSIVU,
      'GET',
      "/global-menus/$menu_type"
    );
  }
}
