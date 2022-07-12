<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation\Plugin\Block;

use Drupal\Core\Menu\MenuActiveTrailInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\helfi_navigation\ExternalMenuBlockInterface;
use Drupal\helfi_navigation\ExternalMenuTree;
use Drupal\helfi_navigation\ExternalMenuTreeFactory;
use Drupal\helfi_navigation\Service\GlobalNavigationService;
use Drupal\system\Plugin\Block\SystemMenuBlock;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for creating external menu blocks.
 */
abstract class ExternalMenuBlockBase extends SystemMenuBlock implements ContainerFactoryPluginInterface, ExternalMenuBlockInterface {

  /**
   * Constructs an instance of ExternalMenuBlockBase.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Menu\MenuLinkTreeInterface $menu_tree
   *   The menu tree service.
   * @param \Drupal\Core\Menu\MenuActiveTrailInterface $menu_active_trail
   *   The active menu trail service.
   * @param \Drupal\helfi_navigation\ExternalMenuTreeFactory $menuTreeFactory
   *   Factory class for creating an instance of ExternalMenuTree.
   * @param \Drupal\helfi_navigation\Service\GlobalNavigationService $globalNavigationService
   *   Global navigation service.
   */
  public function __construct(array $configuration, string $plugin_id, array $plugin_definition, MenuLinkTreeInterface $menu_tree, MenuActiveTrailInterface $menu_active_trail, protected ExternalMenuTreeFactory $menuTreeFactory, protected GlobalNavigationService $globalNavigationService) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $menu_tree, $menu_active_trail);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('menu.link_tree'),
      $container->get('menu.active_trail'),
      $container->get('helfi_navigation.external_menu_tree_factory'),
      $container->get('helfi_navigation.global_navigation_service')
    );
  }

  /**
   * Build a renderable array from data.
   *
   * @return array|null
   *   Returns the render array.
   */
  public function build():? array {
    $menu_tree = $this->buildFromJson($this->getData());

    if (!$menu_tree) {
      return NULL;
    }

    $items = $menu_tree->getTree();

    $build = [];

    if ($items) {
      $build['#sorted'] = TRUE;
      $build['#theme'] = 'menu__external_menu';
      $build['#items'] = $items;
    }

    return $build;
  }

  /**
   * Build menu from JSON.
   *
   * @param string $json
   *   JSON string to generate menu tree from.
   *
   * @return \Drupal\helfi_navigation\ExternalMenuTree|null
   *   The resulting menu tree.
   */
  protected function buildFromJson(string $json):? ExternalMenuTree {
    $options = [
      'menu_name' => $this->getDerivativeId(),
      'max_depth' => $this->getMaxDepth(),
      'starting_level' => $this->getStartingLevel(),
      'expand_all_items' => $this->getExpandAllItems(),
    ];

    try {
      return $this->menuTreeFactory->fromJson($json, $options);
    }
    catch (\throwable $e) {
      return NULL;
    }
  }

  /**
   * Returns the starting level of the menu.
   *
   * @return int
   *   The starting level.
   */
  public function getStartingLevel(): int {
    return (int) $this->getConfiguration()['level'] ?: 0;
  }

  /**
   * Returns the max depth of the menu.
   *
   * @return int
   *   The max depth.
   */
  public function getMaxDepth(): int {
    return (int) $this->getConfiguration()['depth'] ?: 0;
  }

  /**
   * Returns the information of should the items be expanded by default.
   *
   * @return int
   *   Should the items be expanded.
   */
  public function getExpandAllItems(): int {
    return $this->getConfiguration()['expand_all_items'] ?: 0;
  }

}
