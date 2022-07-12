<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\helfi_api_base\Menu\Menu;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides block plugin definitions for custom menus.
 *
 * @see \Drupal\helfi_navigation\Plugin\Block\ExternalMenuBlock
 */
class ExternalMenuBlock extends DeriverBase implements ContainerDeriverInterface {

  /**
   * The external menus.
   *
   * @var array
   */
  protected array $externalMenus;

  /**
   * Constructs new SystemMenuBlock.
   */
  public function __construct() {
    $this->externalMenus = Menu::MENUS;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static();
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    foreach ($this->externalMenus as $menu) {
      $admin_label = ucfirst(str_replace('-', ' ', $menu));
      $this->derivatives[$menu] = $base_plugin_definition;
      $this->derivatives[$menu]['admin_label'] = 'External - ' . $admin_label;
    }
    return $this->derivatives;
  }

}
