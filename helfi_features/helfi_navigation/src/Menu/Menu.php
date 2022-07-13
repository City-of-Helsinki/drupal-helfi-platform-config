<?php

namespace Drupal\helfi_navigation\Menu;

class Menu {
  const MENUS = [
    'footer-bottom-navigation',
    'footer-top-navigation',
    'footer-top-navigation-2',
    'header-top-navigation',
    'main',
  ];

  const FOOTER_TOP_NAVIGATION = 'footer-top-navigation';
  const FOOTER_TOP_NAVIGATION_2 = 'footer-top-navigation-2';
  const FOOTER_BOTTOM_NAVIGATION = 'footer-bottom-navigation';
  const HEADER_TOP_NAVIGATION = 'header-top-navigation';
  const MAIN_MENU = 'main';

  public static function menuExists(string $menu_type = ''): bool {
    return $menu_type && in_array($menu_type, self::MENUS);
  }
}
