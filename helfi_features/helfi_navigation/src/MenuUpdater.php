<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation;

use Drupal\Core\Config\ConfigFactory;
use Drupal\helfi_navigation\Menu\Menu;
use Drupal\helfi_navigation\Menu\MenuTreeBuilder;

/**
 * Synchronizes global menu.
 */
final class MenuUpdater {

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\Config\ConfigFactory $config
   *   The config factory.
   * @param \Drupal\helfi_navigation\ApiManager $apiManager
   *   The api manager.
   * @param \Drupal\helfi_navigation\Menu\MenuTreeBuilder $menuTreeBuilder
   *   The menu builder.
   */
  public function __construct(
    private ConfigFactory $config,
    private ApiManager $apiManager,
    private MenuTreeBuilder $menuTreeBuilder,
  ) {
  }

  /**
   * Sends main menu tree to frontpage instance.
   */
  public function syncMenu(string $langcode): void {
    if (!$authKey = $this->config->get('helfi_navigation.api')->get('key')) {
      throw new \InvalidArgumentException('Missing required "helfi_navigation.api" setting.');
    }
    $tree = $this
      ->menuTreeBuilder
      ->buildMenuTree(Menu::MAIN_MENU, $langcode);

    $siteName = $this->config->get('system.site')->get('name');

    $this->apiManager->updateMainMenu(
      $langcode,
      'Basic ' . $authKey,
      [
        'langcode' => $langcode,
        'site_name' => $siteName,
        'menu_tree' => [
          'name' => $siteName,
          'external' => FALSE,
          'hasItems' => !(empty($tree)),
          'weight' => 0,
          'sub_tree' => $tree,
        ],
      ]
    );
  }

}
