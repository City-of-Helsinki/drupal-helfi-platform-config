<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation\Menu;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\MenuLinkInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\helfi_api_base\Link\InternalDomainResolver;

/**
 * Create menu tree from Drupal menu.
 */
final class MenuTreeBuilder {

  /**
   * Constructs MenuUpdater.
   */
  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
    private InternalDomainResolver $domainResolver,
    private MenuLinkTreeInterface $menuTree
  ) {
  }

  /**
   * Builds menu tree for synchronization.
   *
   * @param string $menuName
   *   Menu type.
   * @param string $langcode
   *   Language code.
   *
   * @return array
   *   The resulting tree.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function buildMenuTree(string $menuName, string $langcode): array {
    $tree = $this->menuTree->load(
      $menuName,
      (new MenuTreeParameters())
        ->onlyEnabledLinks()
    );

    return $this->transformMenuItems($tree, $langcode);
  }

  /**
   * Transform menu items to response format.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeElement[] $menuItems
   *   Array of menu items.
   * @param string $langcode
   *   Language code as a string.
   *
   * @return array
   *   Returns an array of transformed menu items.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function transformMenuItems(array $menuItems, string $langcode): array {
    $items = [];

    foreach ($menuItems as $element) {
      $sub_tree = $element->subtree;

      /** @var \Drupal\menu_link_content\Entity\MenuLinkContent $link */
      if (!$link = $this->getEntity($element->link)) {
        continue;
      }

      // Handle only menu links with translations.
      if (
        !$link->hasTranslation($langcode) ||
        !$link->isTranslatable()
      ) {
        continue;
      }

      /** @var \Drupal\Core\Menu\MenuLinkInterface $menuLink */
      $menuLink = $link->getTranslation($langcode);

      // Handle only published menu links.
      if (!$menuLink->isPublished()) {
        continue;
      }

      $item = [
        'id' => $menuLink->getPluginId(),
        'name' => $menuLink->getTitle(),
        'parentId' => $menuLink->getParentId(),
        'url' => $menuLink->getUrlObject()->setAbsolute()->toString(),
        'external' => $this->domainResolver->isExternal($menuLink->getUrlObject()),
        'hasItems' => FALSE,
        'weight' => $menuLink->getWeight(),
      ];

      if (count($sub_tree) > 0) {
        $item['hasItems'] = TRUE;
        $item['sub_tree'] = $this->transformMenuItems($sub_tree, $langcode);
      }

      $items[] = (object) $item;
    }

    usort($items, [$this, 'sortMenuItems']);
    return $items;
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
  protected function getEntity(MenuLinkInterface $link): ? EntityInterface {
    // MenuLinkContent::getEntity() has protected visibility and cannot be used
    // to directly fetch the entity.
    $metadata = $link->getMetaData();

    if (empty($metadata['entity_id'])) {
      return NULL;
    }
    return $this->entityTypeManager
      ->getStorage('menu_link_content')
      ->load($metadata['entity_id']);
  }

  /**
   * Sort menu items by weight.
   *
   * @param object $item1
   *   First object.
   * @param object $item2
   *   Second object.
   *
   * @return int
   *   Returns sorting order.
   */
  private function sortMenuItems(object $item1, object $item2): int {
    if ($item1->weight == $item2->weight) {
      return 0;
    }
    return $item1->weight < $item2->weight ? -1 : 1;
  }

}
