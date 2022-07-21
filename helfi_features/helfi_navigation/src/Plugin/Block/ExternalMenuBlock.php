<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation\Plugin\Block;

use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_navigation\ExternalMenuTree;

/**
 * Provides an external menu block.
 *
 * @Block(
 *   id = "external_menu_block",
 *   admin_label = @Translation("External menu block"),
 *   category = @Translation("External menu"),
 *   deriver = "Drupal\helfi_navigation\Plugin\Derivative\ExternalMenuBlock"
 * )
 */
class ExternalMenuBlock extends ExternalMenuBlockBase {

  /**
   * Build either fallback menu or external menu tree render array.
   *
   * @return array|null
   *   Returns the render array.
   */
  public function build():? array {
    $build = [];

    $menu_type = $this->getDerivativeId();

    /** @var \Drupal\helfi_navigation\ExternalMenuTree $menu_tree */
    $menu_tree = $this->buildFromJson(
      $this->globalNavigationService->makeRequest(
        Project::ETUSIVU,
        'GET',
        "/global-menus/$menu_type"
      )
    );

    if ($menu_tree instanceof ExternalMenuTree) {
      $build['#sorted'] = TRUE;
      $build['#items'] = $menu_tree->getTree();
      $build['#theme'] = 'menu__external_menu';
      $build['#menu_type'] = $menu_type;
    }

    return $build;
  }

}
