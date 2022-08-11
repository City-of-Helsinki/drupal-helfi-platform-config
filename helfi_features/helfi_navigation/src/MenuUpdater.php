<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation;

use Drupal\Core\Config\ConfigFactory;
use Drupal\helfi_navigation\Menu\Menu;
use Drupal\helfi_navigation\Menu\MenuTreeBuilder;
use Drupal\helfi_navigation\Service\GlobalNavigationService;

/**
 * Synchronizes global menu.
 */
final class MenuUpdater {

  /**
   * Constructs MenuUpdater.
   */
  public function __construct(
    private ConfigFactory $config,
    private GlobalNavigationService $globalNavigationService,
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

    $this->globalNavigationService->updateMainMenu(
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
