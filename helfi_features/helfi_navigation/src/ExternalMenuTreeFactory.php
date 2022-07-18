<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Menu\MenuActiveTrailInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Template\Attribute;
use Drupal\helfi_api_base\Environment\EnvironmentResolver;
use Drupal\helfi_api_base\Link\UrlHelper;
use Drupal\helfi_navigation\Plugin\Menu\ExternalMenuLink;
use Drupal\helfi_navigation\Service\GlobalNavigationService;
use Drupal\helfi_navigation\Validator\ExternalMenuValidator;
use function GuzzleHttp\json_decode;
use JsonSchema\Validator;
use Psr\Log\LoggerInterface;
use Drupal\helfi_api_base\Link\InternalDomainResolver;

/**
 * Helper class for external menu tree actions.
 */
class ExternalMenuTreeFactory {

  /**
   * The JSON schema.
   *
   * @var object
   */
  protected object $schema;

  /**
   * The JSON validator.
   *
   * @var \JsonSchema\Validator
   */
  protected Validator $validator;

  /**
   * Constructs a tree instance from supplied JSON.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger channel.
   * @param \Drupal\helfi_api_base\Link\InternalDomainResolver $domainResolver
   *   Internal domain resolver.
   * @param \Drupal\helfi_api_base\Environment\EnvironmentResolver $environmentResolver
   *   EnvironmentResolver helper class.
   * @param \Drupal\helfi_navigation\Service\GlobalNavigationService $globalNavigationService
   *   Global navigation service.
   * @param \Drupal\Component\Uuid\UuidInterface $uuidService
   *   UUID service.
   * @param \Drupal\Core\Menu\MenuActiveTrailInterface $menuActiveTrail
   *   The active menu trail service.
   * @param \Drupal\Core\Menu\MenuLinkTreeInterface $menuTree
   *   The active menu trail service.
   * @param \Drupal\helfi_navigation\Validator\ExternalMenuValidator $menuValidator
   *   Json validator.
   */
  public function __construct(
    protected LoggerInterface $logger,
    protected InternalDomainResolver $domainResolver,
    protected EnvironmentResolver $environmentResolver,
    protected GlobalNavigationService $globalNavigationService,
    protected UuidInterface $uuidService,
    protected MenuActiveTrailInterface $menuActiveTrail,
    protected MenuLinkTreeInterface $menuTree,
    protected ExternalMenuValidator $menuValidator,
  ) {
  }

  /**
   * Form and return a menu tree instance from json input.
   *
   * @param string $json
   *   The JSON string.
   * @param array $options
   *   Options for the menu link item handling.
   *
   * @return \Drupal\helfi_navigation\ExternalMenuTree|null
   *   The resulting menu tree instance.
   *
   * @throws \Exception
   *   Throws exception.
   */
  public function fromJson(string $json, array $options = []):? ExternalMenuTree {
    $data = (array) json_decode($json);

    if (!$this->menuValidator->validate($data)) {
      throw new \Exception('Invalid JSON input');
    }

    $options += ['active_trail' => $this->menuActiveTrail->getActiveTrailIds($options['menu_name'])];
    $tree = $this->transformItems($data, $options);

    if ($options['fallback']) {
      $tree = $this->buildActiveTrailMenu($options);
    }

    if (!empty($tree)) {
      return new ExternalMenuTree($tree);
    }

    return NULL;
  }

  /**
   * Create menu link items from JSON elements.
   *
   * @param array $items
   *   Provided JSON input.
   * @param array $options
   *   Keyed array of options needed to create menu link items.
   *
   * @return array
   *   Resulting array of menu links.
   */
  protected function transformItems(array $items, array $options): array {
    $transformed_items = [];
    extract($options);

    foreach ($items as $key => $item) {
      $menu_name = $menu_name ?: $item->menu_type;

      // Convert site to menu link item.
      if (isset($item->project) && isset($item->menu_tree)) {
        $item = $item->menu_tree;
      }

      $transformed_item = $this->createLink($item, $menu_name, $active_trail, (bool) $expand_all_items);

      // Make sure there's parent ids in subtree items.
      foreach ($item->sub_tree as &$sub_tree_item) {
        if (empty($sub_tree_item->parentId)) {
          $sub_tree_item->parentId = $transformed_item['id'];
        }
      }

      $options = [
        'active_trail' => $active_trail,
        'max_depth' => $max_depth,
        'menu_name' => $menu_name,
        'expand_all_items' => $expand_all_items,
        'level' => $level + 1,
      ];

      $transformed_item['below'] = (isset($item->sub_tree) && $level < $max_depth)
        ? $this->transformItems($item->sub_tree, $options)
        : [];

      $transformed_items[] = $transformed_item;
    }

    usort($transformed_items, function ($a, $b) {
      return $a['original_link']->getWeight() - $b['original_link']->getWeight();
    });

    return $transformed_items;
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
    $item->url = UrlHelper::parse($item->url);

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
    $project_url = $this->globalNavigationService->getProjectUrl(
      $this->globalNavigationService->getCurrentProject()->getId()
    );

    return (
      $project_url === $item->url->getUri() ||
      in_array($item->id, $active_trail)
    );
  }

}
