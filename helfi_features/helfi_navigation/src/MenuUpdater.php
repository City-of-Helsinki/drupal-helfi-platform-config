<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_navigation\Service\GlobalNavigationService;
use Drupal\menu_link_content\Plugin\Menu\MenuLinkContent;

/**
 * Synchronizes global menu.
 */
class MenuUpdater {
  /**
   * Main menu machine name.
   */
  protected const MAIN_MENU = 'main';

  /**
   * Max depth for menu item synchronization.
   */
  protected const MAX_DEPTH = 2;

  /**
   * Constructs MenuUpdater.
   */
  public function __construct(
    protected ConfigFactory $config,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected GlobalNavigationService $globalNavigationService
  ) {}

  /**
   * Sends main menu tree to frontpage instance.
   *
   * @param string|null $lang_code
   *   Language code as string.
   *
   * @throws \Exception
   *   Throws exception.
   */
  public function syncMenu(string $lang_code = NULL): void {
    if ($this->globalNavigationService->inFrontPage()) {
      return;
    }

    if (!$lang_code) {
      throw new \Exception('No language code set for the menu updater.');
    }

    $currentProject = $this->globalNavigationService->getCurrentProject();
    $siteName = $this->config->get('system.site')->get('name');

    $options = [
      'json' => [
        'name' => $siteName,
        'langcode' => $lang_code,
        'menu_tree' => (object) [
          'name' => $siteName,
          'url' => $currentProject['url'],
          'id' => $currentProject['id'],
          'menu_tree' => $this->buildMenuTree(),
        ],
      ],
    ];

    // @todo Fix this.
    $endpoint = '/global-menus/' . $currentProject['id'];
    $this->globalNavigationService->makeRequest(Project::ETUSIVU, 'POST', $endpoint, $options);
  }

  /**
   * Builds menu tree for synchronization.
   *
   * @return array
   *   The resulting tree.
   */
  protected function buildMenuTree(): array {
    // @todo figure out if we can load the menu tree based on langcode.
    $drupal_tree = \Drupal::menuTree()->load(self::MAIN_MENU, new MenuTreeParameters());
    return $this->transformMenuItems($drupal_tree);
  }

  /**
   * Transform menu items to response format.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeElement[] $menu_items
   *   Array of menu items.
   */
  protected function transformMenuItems(array $menu_items): array {
    $transformed_items = [];

    // @todo Needs to handle the languages (lang codes).

    foreach ($menu_items as $menu_item) {
      /** @var \Drupal\menu_link_content\Plugin\Menu\MenuLinkContent $menu_link */
      $menu_link = $menu_item->link;
      $sub_tree = $menu_item->subtree;

      if (!$entity = $this->getEntity($menu_link)) {
        continue;
      }

      $translatable = $entity->isTranslatable();

      // @todo Needs to handle the languages (lang codes).
      if ($translatable && !$entity->hasTranslation($this->langcode)) {
        continue;
      }

      // @todo Needs to handle the languages (lang codes).
      if ($translatable) {
        $menu_link = $entity->getTranslation($this->langcode);
      }

      $transformedItem = [
        'id' => $menu_link->getPluginId(),
        'name' => $menu_link->getTitle(),
        'url' => $menu_link->getUrlObject()->setAbsolute()->toString(),
      ];

      if (count($sub_tree) > 0 && $menu_item->depth < self::MAX_DEPTH) {
        $transformedItem['sub_tree'] = $this->transformMenuItems($sub_tree);
      }

      $transformed_items[] = (object) $transformedItem;
    }

    return $transformed_items;
  }

  /**
   * Load entity with given menu link.
   *
   * @param \Drupal\menu_link_content\Plugin\Menu\MenuLinkContent $link
   *   The menu link.
   *
   * @return bool|\Drupal\Core\Entity\EntityInterface|null
   *   Boolean if menu link has no metadata. NULL if entity not found and
   *   an EntityInterface if found.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getEntity(MenuLinkContent $link): EntityInterface|bool|null {
    // MenuLinkContent::getEntity() has protected visibility and cannot be used
    // to directly fetch the entity.
    $metadata = $link->getMetaData();

    if (empty($metadata['entity_id'])) {
      return FALSE;
    }
    return $this->entityTypeManager
      ->getStorage('menu_link_content')
      ->load($metadata['entity_id']);
  }

}
