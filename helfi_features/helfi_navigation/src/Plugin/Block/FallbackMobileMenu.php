<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation\Plugin\Block;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Template\Attribute;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\menu_link_content\MenuLinkContentInterface;

/**
 * Provides a fallback mobile navigation menu block.
 *
 * This is used to render the non-javascript version of mobile
 * navigation.
 *
 * @Block(
 *   id = "external_menu_block_fallback",
 *   admin_label = @Translation("External - Fallback mobile menu"),
 *   category = @Translation("External menu"),
 * )
 */
class FallbackMobileMenu extends ExternalMenuBlockBase {

  /**
   * Build the fallback menu render array.
   *
   * @return array
   *   Returns the render array.
   */
  public function build() : array {
    $build = [];

    // Create fallback menu render array.
    try {
      $build = $this->buildActiveTrailMenu($this->getOptions());
      $build['#theme'] = 'menu__external_menu__fallback';
    }
    catch (\throwable $e) {
      $this->logger->error('External fallback menu build failed with error: ' . $e->getMessage());
    }

    return $build;
  }

  /**
   * Build menu tree based on active trail.
   *
   * @param array $options
   *   Options for the menu block.
   *
   * @return array
   *   Render array.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function buildActiveTrailMenu(array $options): array {

    // Adjust the menu tree parameters based on the block's configuration.
    $parameters = $this->menuTree->getCurrentRouteMenuTreeParameters(
      $options['menu_type']
    );
    $depth = $options['max_depth'];
    if ($options['expand_all_items']) {
      $parameters->expandedParents = [];
    }

    // Update the level to match the active menu item's level in the menu.
    $level = count($parameters->activeTrail);
    $active_trail = $parameters->activeTrail;

    // Get active menu link, aka. current menu link.
    /** @var \Drupal\menu_link_content\MenuLinkContentInterface $active_menu_link */
    $active_menu_link = $this->getActiveMenuLink();
    $last_link_of_tree = $active_menu_link && $this->menuLinkHasChildren(
      $active_menu_link
    );

    // Set min and max depth variables for the menu tree parameters.
    $parameters->setMinDepth($level);
    $parameters->setMaxDepth($depth);

    // If active menu link is last of its kind, treat the menu tree as the
    // link would really appear on second to last level.
    $offset = ($last_link_of_tree) ? 2 : 1;

    // Active trail array is child-first. Reverse it, and pull the new menu
    // root based on the parent of the configured start level.
    $menu_trail_ids = array_reverse(array_values($parameters->activeTrail));
    $menu_root = $menu_trail_ids[$level - $offset];
    $parameters->setRoot($menu_root)->setMinDepth(1);

    // Load the menu tree with.
    $tree = $this->menuTree->load($options['menu_type'], $parameters);
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];

    // Get the grandparent link for the fallback menu.
    $grand_parents = array_slice($active_trail, 2);
    $grand_parent_link_id = reset($grand_parents);
    $grand_parent_link = (!empty($grand_parent_link_id))
      ? $this->loadMenuLinkByDerivativeId($grand_parent_link_id)
      : $this->getSiteFrontPageMenuLink();
    if ($grand_parent_link instanceof MenuLinkContentInterface) {
      $grand_parent_link = $this->createRenderArrayFromMenuLinkContent($grand_parent_link);
    }

    // Get the parent link for the fallback menu.
    $parents = array_slice($active_trail, 1);
    $parent_link_id = reset($parents);
    $parent_link = (!empty($parent_link_id))
      ? $this->loadMenuLinkByDerivativeId($parent_link_id)
      : $this->getSiteFrontPageMenuLink();
    if ($parent_link instanceof MenuLinkContentInterface) {
      $parent_link = $this->createRenderArrayFromMenuLinkContent($parent_link);
    }

    // If the current menu link available, create back and current/parent links
    // for the fallback menu.
    if ($active_menu_link) {
      if ($last_link_of_tree) {
        $menu_link_back = $grand_parent_link;
        $menu_link_current_or_parent = $parent_link;
      }
      else {
        $menu_link_back = $parent_link;
        $menu_link_current_or_parent = $this->createRenderArrayFromMenuLinkContent($this->getActiveMenuLink());
      }
    }

    // If the current menu link is not available, we're most likely browsing
    // the front page or first level of the menu tree.
    // Create back and current/parent links accordingly.
    else {
      $menu_link_back = [
        'title' => $this->t('Front page'),
        'url' => $this->apiManager->getProjectUrl(Project::ETUSIVU),
      ];
      $menu_link_current_or_parent = $grand_parent_link;
    }

    // Build a render array and enable proper caching.
    $tree = $this->menuTree->transform($tree, $manipulators);
    $build = $this->menuTree->build($tree);
    $build['#menu_link_back'] = $menu_link_back;
    $build['#menu_link_current_or_parent'] = $menu_link_current_or_parent;
    $build['#cache'] = [
      'contexts' => [
        'url',
        'route.menu_active_trails:' . $this->getMenuType(),
      ],
      'tags' => [
        'config:system.menu.' . $this->getMenuType(),
      ],
    ];

    return $build;
  }

  /**
   * Get menu block options.
   *
   * @return array
   *   Returns the options as an array.
   */
  protected function getOptions(): array {
    return [
      'menu_type' => 'main',
      'max_depth' => $this->getMaxDepth(),
      'level' => $this->getStartingLevel(),
      'expand_all_items' => $this->getExpandAllItems(),
      'fallback' => TRUE,
    ];
  }

  /**
   * Get menu type.
   *
   * @return string
   *   Returns the menu type as a string.
   */
  protected function getMenuType(): string {
    return $this->getOptions()['menu_type'];
  }

  /**
   * Gets the active menu item.
   *
   * @return \Drupal\menu_link_content\MenuLinkContentInterface|bool
   *   Returns the currently active menu item or FALSE.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function getActiveMenuLink(): MenuLinkContentInterface|bool {
    $active_menu_link = FALSE;
    $active_trail_id = $this->getDerivativeActiveTrailIds();
    $active_trail_id = reset($active_trail_id);
    if ($active_trail_id) {
      /** @var \Drupal\menu_link_content\MenuLinkContentInterface $active_menu_link */
      $active_menu_link = $this->loadMenuLinkByDerivativeId($active_trail_id);
    }
    return $active_menu_link;
  }

  /**
   * Checks if given menu link content entity has children.
   *
   * @param \Drupal\menu_link_content\MenuLinkContentInterface $menu_link_content
   *   Menu link content entity.
   *
   * @return bool
   *   Returns true or false depending of the children.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function menuLinkHasChildren(MenuLinkContentInterface $menu_link_content): bool {
    $children = $this->entityTypeManager
      ->getStorage('menu_link_content')
      ->loadByProperties([
        'menu_name' => $this->getMenuType(),
        'enabled' => 1,
        'parent' => $menu_link_content->getPluginId(),
      ]);
    return count($children) == 0;
  }

  /**
   * Load menu link content entity with menu link content derivative id.
   *
   * @param bool|string $id
   *   Menu link derivative id.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   Returns menu link content entity or NULL.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function loadMenuLinkByDerivativeId(bool|string $id): ?EntityInterface {
    $menu_link = NULL;
    if ($id) {
      $menu_link = $this->entityRepository
        ->loadEntityByUuid('menu_link_content', $this->convertToUuid($id));
    }
    return $menu_link;
  }

  /**
   * Gets an array of the active trail menu link items.
   *
   * @return array
   *   The active trail menu item IDs.
   */
  protected function getDerivativeActiveTrailIds(): array {
    return array_filter($this->menuActiveTrail->getActiveTrailIds($this->getMenuType()));
  }

  /**
   * Get current instance front page menu link.
   *
   * @return array
   *   Returns current instance front page menu link array.
   */
  protected function getSiteFrontPageMenuLink(): array {
    $language = $this->languageManager->getCurrentLanguage()->getId();
    $prefixes = $this->configFactory->get('helfi_proxy.settings')->get('prefixes');
    $url = $this->apiManager->getCurrentProject()->getUrl($language);

    return [
      'is_currentPage' => (
        isset($prefixes[$language]) &&
        \Drupal::request()->getUri() == "$url/$prefixes[$language]"
      ),
      'attributes' => new Attribute(),
      'title' => $this->getSiteName(),
      'url' => $url,
    ];
  }

  /**
   * Get current site name for the menu.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
   *   Returns site name as string or translated front page text.
   */
  protected function getSiteName(): mixed {
    $config_override_language = $this->languageManager->getCurrentLanguage();
    $this->languageManager->setConfigOverrideLanguage($this->languageManager->getCurrentLanguage());
    $site_name = $this->configFactory->get('system.site')->getOriginal('name');
    $this->languageManager->setConfigOverrideLanguage($config_override_language);
    return !empty($site_name) ? $site_name : $this->t('Front page');
  }

  /**
   * Create simple render array from menu link content.
   *
   * @param \Drupal\menu_link_content\MenuLinkContentInterface $menu_link_content
   *   Menu link content entity.
   *
   * @return array
   *   Returns an array of menu link content title and URL object.
   */
  protected function createRenderArrayFromMenuLinkContent(MenuLinkContentInterface $menu_link_content): array {
    $current_path = \Drupal::request()->getRequestUri();
    return [
      'is_currentPage' => $menu_link_content->getUrlObject()->toString() == $current_path,
      'title' => $menu_link_content->getTitle(),
      'url' => $menu_link_content->getUrlObject(),
    ];
  }

  /**
   * Convert derivative ID to UUID.
   *
   * @param string $derivative_id
   *   The derivative ID to be converted.
   * @param string $type
   *   The optional type, defaults to menu_link_content.
   *
   * @return string
   *   Returns UUID.
   */
  protected function convertToUuid(string $derivative_id, string $type = 'menu_link_content'): string {
    return str_replace("$type:", '', $derivative_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() : array {
    $cache_tags = parent::getCacheTags();
    $cache_tags[] = 'config:system.menu.' . $this->getMenuType();
    return $cache_tags;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() : array {
    return Cache::mergeContexts(
      parent::getCacheContexts(),
      ['route.menu_active_trails:' . $this->getMenuType()]
    );
  }

  protected function buildMenuTree(): array {
    // TODO: Implement buildMenuTree() method.
  }

}
