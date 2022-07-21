<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation\Plugin\Block;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Menu\MenuActiveTrailInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Template\Attribute;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_navigation\ExternalMenuBlockInterface;
use Drupal\helfi_navigation\ExternalMenuTree;
use Drupal\helfi_navigation\ExternalMenuTreeFactory;
use Drupal\helfi_navigation\Service\GlobalNavigationService;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\system\Plugin\Block\SystemMenuBlock;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for creating external menu blocks.
 */
abstract class ExternalMenuBlockBase extends SystemMenuBlock implements ContainerFactoryPluginInterface, ExternalMenuBlockInterface {

  /**
   * Constructs an instance of ExternalMenuBlockBase.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactory $configFactory
   *   The config factory service.
   * @param \Drupal\Core\Path\PathMatcherInterface $pathMatcher
   *   The path matcher service.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository
   *   The entity repository service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager service.
   * @param \Drupal\Core\Menu\MenuLinkTreeInterface $menu_tree
   *   The menu tree service.
   * @param \Drupal\Core\Menu\MenuActiveTrailInterface $menu_active_trail
   *   The active menu trail service.
   * @param \Drupal\helfi_navigation\ExternalMenuTreeFactory $menuTreeFactory
   *   Factory class for creating an instance of ExternalMenuTree.
   * @param \Drupal\helfi_navigation\Service\GlobalNavigationService $globalNavigationService
   *   Global navigation service.
   */
  public function __construct(array $configuration, string $plugin_id, array $plugin_definition, protected ConfigFactory $configFactory, protected PathMatcherInterface $pathMatcher, protected EntityRepositoryInterface $entityRepository, protected EntityTypeManagerInterface $entityTypeManager, protected LanguageManagerInterface $languageManager, MenuLinkTreeInterface $menu_tree, MenuActiveTrailInterface $menu_active_trail, protected ExternalMenuTreeFactory $menuTreeFactory, protected GlobalNavigationService $globalNavigationService) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $menu_tree, $menu_active_trail);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('path.matcher'),
      $container->get('entity.repository'),
      $container->get('entity_type.manager'),
      $container->get('language_manager'),
      $container->get('menu.link_tree'),
      $container->get('menu.active_trail'),
      $container->get('helfi_navigation.external_menu_tree_factory'),
      $container->get('helfi_navigation.global_navigation_service')
    );
  }

  /**
   * Build either fallback menu or external menu tree render array.
   *
   * @return array|null
   *   Returns the render array.
   */
  public function build():? array {
    $build = [];

    // Create either fallback menu or external menu.
    if ($this->getFallback()) {
      $build = $this->buildActiveTrailMenu($this->getOptions());
      $build['#theme'] = 'menu__external_menu__fallback';
    }
    else {
      /** @var \Drupal\helfi_navigation\ExternalMenuTree $menu_tree */
      $menu_tree = $this->buildFromJson($this->getData());
      if ($menu_tree instanceof ExternalMenuTree) {
        $build['#sorted'] = TRUE;
        $build['#items'] = $menu_tree->getTree();
        $build['#theme'] = 'menu__external_menu';
        $build['#menu_type'] = $this->getDerivativeId();
      }
    }

    return $build;
  }

  /**
   * {@inheritDoc}
   */
  public function getFallback(): bool {
    return $this->getConfiguration()['fallback'] ?: FALSE;
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
        'url' => $this->globalNavigationService->getProjectUrl(Project::ETUSIVU),
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
        'route.menu_active_trails:' . $this->getDerivativeId(),
      ],
      'tags' => ['config:system.menu.main'],
    ];

    return $build;
  }

  /**
   * Gets an array of the active trail menu link items.
   *
   * @return array
   *   The active trail menu item IDs.
   */
  protected function getDerivativeActiveTrailIds(): array {
    $menu_id = $this->getDerivativeId();
    return array_filter($this->menuActiveTrail->getActiveTrailIds($menu_id));
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
   * Get current instance front page menu link.
   *
   * @return array
   *   Returns current instance front page menu link array.
   */
  protected function getSiteFrontPageMenuLink(): array {
    $language = $this->languageManager->getCurrentLanguage()->getId();
    $prefixes = $this->configFactory->get('helfi_proxy.settings')->get('prefixes');
    $url = $this->globalNavigationService->getCurrentProject()->getUrl($language);

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
        'menu_name' => 'main',
        'enabled' => 1,
        'parent' => $menu_link_content->getPluginId(),
      ]);
    return count($children) == 0;
  }

  /**
   * Get menu block options.
   *
   * @return array
   *   Returns the options as an array.
   */
  protected function getOptions(): array {
    return [
      'menu_type' => $this->getDerivativeId(),
      'max_depth' => $this->getMaxDepth(),
      'level' => $this->getStartingLevel(),
      'expand_all_items' => $this->getExpandAllItems(),
      'fallback' => $this->getFallback(),
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function getMaxDepth(): int {
    $max_depth = $this->getConfiguration()['depth'];
    return $max_depth == 0 ? 10 : $max_depth;
  }

  /**
   * {@inheritDoc}
   */
  public function getStartingLevel(): int {
    return (int) $this->getConfiguration()['level'] ?: 0;
  }

  /**
   * {@inheritDoc}
   */
  public function getExpandAllItems(): bool {
    return $this->getConfiguration()['expand_all_items'] ?: FALSE;
  }

  /**
   * Build menu from JSON.
   *
   * @param string $json
   *   JSON string to generate menu tree from.
   *
   * @return \Drupal\helfi_navigation\ExternalMenuTree|null
   *   The resulting menu tree.
   */
  protected function buildFromJson(string $json):? ExternalMenuTree {
    try {
      return $this->menuTreeFactory->fromJson($json, $this->getOptions());
    }
    catch (\throwable $e) {
      return NULL;
    }
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
  public function blockForm($form, FormStateInterface $form_state): array {
    $config = $this->configuration;
    $defaults = $this->defaultConfiguration();
    $form = parent::blockForm($form, $form_state);

    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced options'),
      '#open' => FALSE,
      '#process' => [[get_class(), 'processFieldSets']],
    ];

    $form['advanced']['fallback'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('<strong>Make the initial visibility level follow the active menu item and render menu as fallback menu.</strong>'),
      '#default_value' => $config['fallback'],
    ];

    // Open the detail field sets if their config is not set to default values.
    foreach (array_keys($form['advanced']) as $field) {
      if (isset($defaults[$field]) && $defaults[$field] !== $config[$field]) {
        $form['advanced']['#open'] = TRUE;
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'fallback' => 0,
      'level' => 1,
      'depth' => 0,
      'expand_all_items' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['fallback'] = $form_state->getValue('fallback');
    $this->configuration['level'] = $form_state->getValue('level');
    $this->configuration['depth'] = $form_state->getValue('depth');
    $this->configuration['expand_all_items'] = (bool) $form_state->getValue('expand_all_items');
  }

  /**
   * Convert form elements to more suitable format for the configurations.
   *
   * @param array $element
   *   Element what needs conversion.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   * @param array $form
   *   Complete form.
   *
   * @return array
   *   Returns the element.
   */
  public static function processFieldSets(array &$element, FormStateInterface $form_state, array &$form): array {
    array_pop($element['#parents']);
    return $element;
  }

}
