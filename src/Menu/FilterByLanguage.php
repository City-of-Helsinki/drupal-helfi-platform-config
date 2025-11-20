<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Menu;

use Drupal\Core\Menu\MenuLinkTreeManipulatorsAlterEvent;
use Drupal\Core\Routing\AdminContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Filters out the untranslated menu links.
 *
 * @note this requires a patch from #3091246.
 */
final class FilterByLanguage implements EventSubscriberInterface {

  /**
   * List of menu names to filter.
   *
   * @var string[]
   */
  protected array $menuNames = [
    'footer-bottom-navigation',
    'footer-top-navigation',
    'footer-top-navigation-2',
    'header-top-navigation',
    'header-language-links',
    'main',
  ];

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\Routing\AdminContext $adminContext
   *   The admin context service.
   */
  public function __construct(private AdminContext $adminContext) {
  }

  /**
   * Responds to MenuLinkTreeEvents::ALTER_MANIPULATORS event.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeManipulatorsAlterEvent $event
   *   The event to subscribe to.
   */
  public function filter(MenuLinkTreeManipulatorsAlterEvent $event) : void {
    // Drush defaults to site's default language. In our case, finnish.
    // This causes '::filterLanguages' manipulator to set AccessResultForbidden
    // to all non-finnish links when we build 'helfi_navigation' links via
    // Drush.
    // All normal menus are displayed using blocks provided by Menu block
    // current language module, so this only needs to be run against menu UI
    // links.
    // @see UHF-7615.
    if (!$this->adminContext->isAdminRoute()) {
      return;
    }
    $manipulators = $event->getManipulators();

    $menuName = NULL;
    foreach ($event->getTree() as $item) {
      if (!$item->link) {
        continue;
      }
      $menuName = $item->link->getMenuName();
      break;
    }

    if (!in_array($menuName, $this->menuNames)) {
      return;
    }

    $manipulators[] = [
      'callable' => 'menu_block_current_language_tree_manipulator::filterLanguages',
      'args' => [['menu_link_content']],
    ];
    $event->setManipulators($manipulators);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() : array {
    return [
      MenuLinkTreeManipulatorsAlterEvent::class => [
        ['filter'],
      ],
    ];
  }

}
