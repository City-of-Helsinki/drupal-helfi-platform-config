<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation\Plugin\Block;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Menu\MenuActiveTrailInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\helfi_navigation\ExternalMenuBlockInterface;
use Drupal\helfi_navigation\ExternalMenuTree;
use Drupal\helfi_navigation\ExternalMenuTreeFactory;
use Drupal\helfi_navigation\Service\GlobalNavigationService;
use Drupal\system\Plugin\Block\SystemMenuBlock;
use Psr\Log\LoggerInterface;
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
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger channel.
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
  public function __construct(array $configuration, string $plugin_id, array $plugin_definition, protected LoggerInterface $logger, protected ConfigFactory $configFactory, protected PathMatcherInterface $pathMatcher, protected EntityRepositoryInterface $entityRepository, protected EntityTypeManagerInterface $entityTypeManager, protected LanguageManagerInterface $languageManager, MenuLinkTreeInterface $menu_tree, MenuActiveTrailInterface $menu_active_trail, protected ExternalMenuTreeFactory $menuTreeFactory, protected GlobalNavigationService $globalNavigationService) {
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
      $container->get('logger.channel.helfi_navigation'),
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
      'fallback' => FALSE,
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
      $this->logger->error('Build from JSON failed with error: ' . $e->getMessage());
      return NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    // @todo We should add menu request cache tag here.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return [
      'user.permissions',
      'url.query_args',
      'url.path',
      'languages:language_content',
    ];
  }

}
