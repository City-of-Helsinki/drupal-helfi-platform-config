<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation\Plugin\Block;

use Drupal\helfi_api_base\Environment\Project;

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
   * {@inheritdoc}
   */
  public function getData(): string {
    $menu_type = $this->getDerivativeId();

    return $this->globalNavigationService->makeRequest(
      Project::ETUSIVU,
      'GET',
      "/global-menus/$menu_type"
    );
  }

}
