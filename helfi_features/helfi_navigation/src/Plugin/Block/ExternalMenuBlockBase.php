<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation\Plugin\Block;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\helfi_navigation\ExternalMenuBlockInterface;
use Drupal\helfi_navigation\ExternalMenuTree;
use Drupal\helfi_navigation\ExternalMenuTreeFactory;
use Drupal\helfi_navigation\ApiManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for creating external menu blocks.
 */
abstract class ExternalMenuBlockBase extends MenuBlockBase implements ExternalMenuBlockInterface {

  /**
   * The menu tree factory.
   *
   * @var \Drupal\helfi_navigation\ExternalMenuTreeFactory
   */
  protected ExternalMenuTreeFactory $menuTreeFactory;

  /**
   * The global navigation service.
   *
   * @var \Drupal\helfi_navigation\ApiManager
   */
  protected ApiManager $apiManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) : static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->apiManager = $container->get('helfi_navigation.api_manager');
    $instance->menuTreeFactory = $container->get('helfi_navigation.external_menu_tree_factory');
    $instance->languageManager = $container->get('language_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() : array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() : array {
    return [
      'user.permissions',
      'url.query_args',
      'url.path',
      'languages:language_content',
    ];
  }

  /**
   * Builds the external menu tree.
   *
   * @return array
   *   The external menu tree.
   */
  abstract protected function buildMenuTree() : array;

  /**
   * {@inheritdoc}
   */
  public function build() : array {
    $build = [
      '#cache' => [
        'cache_context' => $this->getCacheContexts(),
        'cache_tags' => $this->getCacheTags(),
      ],
    ];

    $menuId = $this->getDerivativeId();
    $menu_tree = $this->menuTreeFactory
      ->transform($this->buildMenuTree(), $this->getOptions());

    if (!$menu_tree instanceof ExternalMenuTree) {
      return $build;
    }
    $build['#sorted'] = TRUE;
    $build['#items'] = $menu_tree->getTree();
    $build['#theme'] = 'menu__external_menu';
    $build['#menu_type'] = $menuId;

    return $build;
  }

}
