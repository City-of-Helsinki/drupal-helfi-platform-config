<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Menu\MenuLinkInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_api_base\Link\InternalDomainResolver;
use Drupal\helfi_navigation\Service\GlobalNavigationService;

/**
 * Synchronizes global menu.
 */
class MenuUpdater {

  /**
   * Main menu machine name.
   */
  protected const MAIN_MENU = 'main';

  /**
   * Constructs MenuUpdater.
   */
  public function __construct(
    protected ConfigFactory $config,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected GlobalNavigationService $globalNavigationService,
    protected LanguageManagerInterface $languageManager,
    protected InternalDomainResolver $domainResolver,
  ) {}

  /**
   * Sends main menu tree to frontpage instance.
   *
   * @throws \Exception
   *   Throws exception.
   */
  public function syncMenu(): void {
    if ($this->globalNavigationService->inFrontPage()) {
      return;
    }

    $current_project = $this->globalNavigationService->getCurrentProject();

    $options = [
      'json' => [
        'id' => $current_project['id'],
        'url' => $current_project['url'],
        'site_name' => $this->siteNames(),
        'menu_tree' => $this->buildMenuTree(),
      ],
    ];

    $this->globalNavigationService->makeRequest(
      Project::ETUSIVU,
      'POST',
      $this->getGlobalMenuEndpoint(),
      $options
    );
  }

  /**
   * Get translated site names.
   *
   * @return array
   *   Returns site names as an array or empty array.
   */
  protected function siteNames(): array {
    $site_names = [];

    foreach ($this->languageManager->getLanguages() as $language) {
      $this->languageManager->setConfigOverrideLanguage($language);
      $override = $this->languageManager->getDefaultLanguage()->getId() !== $language->getId();
      $site_name = $this->config
        ->get('system.site')
        ->getOriginal('name', $override);

      $site_names[$language->getId()] = $site_name;
    }

    return $site_names;
  }

  /**
   * Builds menu tree for synchronization.
   *
   * @return array
   *   The resulting tree.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function buildMenuTree(): array {
    $menu_tree = [];

    foreach ($this->languageManager->getLanguages() as $lang_code => $language) {
      $tree = \Drupal::menuTree()->load(
        self::MAIN_MENU,
        (new MenuTreeParameters())
          ->onlyEnabledLinks()
      );

      $menu_tree[$lang_code] = $this->transformMenuItems($tree, $lang_code);
    }

    return $menu_tree;
  }

  /**
   * Transform menu items to response format.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeElement[] $menu_items
   *   Array of menu items.
   * @param string $lang_code
   *   Language code as a string.
   *
   * @return array
   *   Returns an array of transformed menu items.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function transformMenuItems(array $menu_items, string $lang_code): array {
    $transformed_items = [];

    foreach ($menu_items as $menu_item) {
      $sub_tree = $menu_item->subtree;

      /** @var \Drupal\menu_link_content\Entity\MenuLinkContent $entity */
      if (!$menu_link_content = $this->getEntity($menu_item->link)) {
        continue;
      }

      // Handle only menu links with translations.
      if (
        !$menu_link_content->hasTranslation($lang_code) ||
        !$menu_link_content->isTranslatable()
      ) {
        continue;
      }

      /** @var MenuLinkInterface $menu_link */
      $menu_link = $menu_link_content->getTranslation($lang_code);

      // Handle only published menu links.
      if (!$menu_link->isPublished()) {
        continue;
      }

      $transformed_item = [
        'id' => $menu_link->getPluginId(),
        'name' => $menu_link->getTitle(),
        'url' => $menu_link->getUrlObject()->setAbsolute()->toString(),
        'external' => $this->domainResolver->isExternal($menu_link->getUrlObject()),
        'hasItems' => FALSE,
        'weight' => $menu_link->getWeight(),
      ];

      if (count($sub_tree) > 0) {
        $transformed_item['hasItems'] = TRUE;
        $transformed_item['sub_tree'] = $this->transformMenuItems($sub_tree, $lang_code);
      }

      $transformed_items[] = (object) $transformed_item;
    }

    usort($transformed_items, [$this, 'sortMenuItems']);
    return $transformed_items;
  }

  /**
   * Load entity with given menu link.
   *
   * @param \Drupal\Core\Menu\MenuLinkInterface $link
   *   The menu link.
   *
   * @return bool|\Drupal\Core\Entity\EntityInterface|null
   *   Boolean if menu link has no metadata. NULL if entity not found and
   *   an EntityInterface if found.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getEntity(MenuLinkInterface $link): EntityInterface|bool|NULL {
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

  /**
   * @return string
   *   Global menu endpoint.
   */
  protected function getGlobalMenuEndpoint(): string {
    return sprintf('/global-menus/%s', $this->globalNavigationService->getCurrentProject()['id']);
  }

  /**
   * Sort menu items by weight.
   *
   * @param $item1
   *   First object.
   * @param $item2
   *   Second object.
   *
   * @return int
   */
  private function sortMenuItems($item1, $item2) {
    $weight1 = $item1->weight;
    $weight2 = $item2->weight;
    if ($weight1 == $weight2) {
      return 0;
    }
    return $weight1 < $weight2 ? -1 : 1;
  }

}
