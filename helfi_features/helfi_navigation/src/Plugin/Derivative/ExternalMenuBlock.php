<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\helfi_navigation\Menu\Menu;

/**
 * Provides block plugin definitions for custom menus.
 *
 * @see \Drupal\helfi_navigation\Plugin\Block\ExternalMenuBlock
 */
final class ExternalMenuBlock extends DeriverBase {

  /**
   * The external menus.
   *
   * @var array
   */
  protected array $externalMenus = Menu::MENUS;

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) : array {
    // @todo Fetch these via API.
    foreach ($this->externalMenus as $menu) {
      $admin_label = ucfirst(str_replace('-', ' ', $menu));
      $this->derivatives[$menu] = $base_plugin_definition;
      $this->derivatives[$menu]['admin_label'] = 'External - ' . $admin_label;
      $this->derivatives[$menu]['config_dependencies']['config'] = [$menu];
    }
    return $this->derivatives;
  }

}
