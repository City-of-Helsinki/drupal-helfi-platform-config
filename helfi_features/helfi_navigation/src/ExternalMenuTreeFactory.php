<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Menu\MenuActiveTrailInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\helfi_api_base\Link\UrlHelper;
use Drupal\helfi_navigation\Plugin\Menu\ExternalMenuLink;
use Psr\Log\LoggerInterface;
use Drupal\helfi_api_base\Link\InternalDomainResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Helper class for external menu tree actions.
 */
class ExternalMenuTreeFactory {

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected Request $currentRequest;

  /**
   * Constructs a tree instance from supplied JSON.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger channel.
   * @param \Drupal\helfi_api_base\Link\InternalDomainResolver $domainResolver
   *   Internal domain resolver.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\helfi_navigation\ApiManager $apiManager
   *   Global navigation service.
   * @param \Drupal\Component\Uuid\UuidInterface $uuidService
   *   UUID service.
   * @param \Drupal\Core\Menu\MenuActiveTrailInterface $menuActiveTrail
   *   The active menu trail service.
   * @param \Drupal\Core\Menu\MenuLinkTreeInterface $menuTree
   *   The active menu trail service.
   */
  public function __construct(
    protected LoggerInterface $logger,
    protected InternalDomainResolver $domainResolver,
    RequestStack $requestStack,
    protected ApiManager $apiManager,
    protected UuidInterface $uuidService,
    protected MenuActiveTrailInterface $menuActiveTrail,
    protected MenuLinkTreeInterface $menuTree
  ) {
    $this->currentRequest = $requestStack->getCurrentRequest();
  }

  /**
   * Form and return a menu tree instance for given menu items.
   *
   * @param array $menu
   *   The menu.
   * @param array $options
   *   Options for the menu link item handling.
   *
   * @return \Drupal\helfi_navigation\ExternalMenuTree|null
   *   The resulting menu tree instance.
   */
  public function transform(array $menu, array $options = []) :? ExternalMenuTree {
    $options += ['active_trail' => $this->menuActiveTrail->getActiveTrailIds($options['menu_type'])];

    $tree = $this->transformItems($menu, $options);

    if (!empty($tree)) {
      return new ExternalMenuTree($tree);
    }
    return NULL;
  }

  /**
   * Create menu link items from JSON elements.
   *
   * @param array $menuItems
   *   Provided menu items.
   * @param array $options
   *   Keyed array of options needed to create menu link items.
   *
   * @return array
   *   Resulting array of menu links.
   */
  protected function transformItems(array $menuItems, array $options): array {
    $items = [];

    [
      'active_trail' => $active_trail,
      'expand_all_items' => $expand_all_items,
      'level' => $level,
      'max_depth' => $max_depth,
      'menu_type' => $menu_type,
    ] = $options;

    foreach ($menuItems as $element) {
      $item = $this->createLink($element, $menu_type, $active_trail, (bool) $expand_all_items);

      $options = [
        'active_trail' => $active_trail,
        'max_depth' => $max_depth,
        'menu_type' => $menu_type,
        'expand_all_items' => $expand_all_items,
        'level' => $level + 1,
      ];

      if (isset($element->sub_tree)) {
        // Make sure there's parent ids in subtree items.
        foreach ($element->sub_tree as &$sub_tree_item) {
          if (empty($sub_tree_item->parentId)) {
            $sub_tree_item->parentId = $item['id'];
          }
        }

        // Handle subtree.
        if ($level < $max_depth) {
          $item['below'] = $this->transformItems($element->sub_tree, $options);
        }
      }

      $items[] = $item;
    }

    usort($items, function ($a, $b) {
      return $a['original_link']->getWeight() - $b['original_link']->getWeight();
    });

    return $items;
  }

  /**
   * Create link from menu tree item.
   *
   * @param object $item
   *   Menu tree item.
   * @param string $menu_name
   *   Menu name.
   * @param array $active_trail
   *   An array of menu link items in active trail.
   * @param bool $expand_all_items
   *   Should the menu link item be expanded.
   *
   * @return array
   *   Returns a menu link.
   */
  protected function createLink(object $item, string $menu_name, array $active_trail, bool $expand_all_items): array {
    $link_definition = [
      'menu_name' => $menu_name,
      'options' => [],
      'title' => $item->name,
    ];

    // Parse the URL.
    $item->url = !empty($item->url) ? UrlHelper::parse($item->url) : new Url('<nolink>');

    if (!isset($item->id)) {
      $item->id = 'menu_link_content:' . $this->uuidService->generate();
    }

    if (!isset($item->parentId)) {
      $item->parentId = NULL;
    }

    if (!isset($item->external)) {
      $item->external = $this->domainResolver->isExternal($item->url);
    }

    if (isset($item->description)) {
      $link_definition['description'] = $item->description;
    }

    if (isset($item->weight)) {
      $link_definition['weight'] = $item->weight;
    }

    return [
      'attributes' => new Attribute(),
      'title' => $item->name,
      'id' => $item->id,
      'parent_id' => $item->parentId,
      'is_expanded' => $expand_all_items,
      'in_active_trail' => $this->inActiveTrail($item, $active_trail),
      'original_link' => new ExternalMenuLink([], $item->id, $link_definition),
      'external' => $item->external,
      'url' => $item->url,
      'below' => [],
    ];
  }

  /**
   * Check if current menu link item is in active trail.
   *
   * @param object $item
   *   Menu link item.
   * @param array $active_trail
   *   An array of active menu links.
   *
   * @return bool
   *   Returns true or false.
   */
  protected function inActiveTrail(object $item, array $active_trail): bool {
    if ($item->url->isRouted() && $item->url->getRouteName() === '<nolink>') {
      return FALSE;
    }
    $currentPath = parse_url($this->currentRequest->getUri(), PHP_URL_PATH);
    $linkPath = parse_url($item->url->getUri(), PHP_URL_PATH);

    // We don't care about the domain when comparing URLs because the
    // site might be served from multiple different domains.
    if ($linkPath === $currentPath || in_array($item->id, $active_trail)) {
      return TRUE;
    }
    return FALSE;
  }

}
