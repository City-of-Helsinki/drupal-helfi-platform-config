<?php

namespace Drupal\helfi_paragraphs_hearings\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides 'Hearings' block.
 *
 * @Block(
 *   id = "hearings",
 *   admin_label = @Translation("Hearings"),
 * )
 */
class HearingBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
          $plugin_id,
          $plugin_definition,
    RouteMatchInterface $route_match,
    EntityTypeManagerInterface $entity_type_manager,
    LanguageManagerInterface $language_manager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routeMatch = $route_match;
    $this->entityTypeManager = $entity_type_manager;
    $this->languageManager = $language_manager;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition,
      $container->get('current_route_match'),
      $container->get('entity_type.manager'),
      $container->get('language_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $entityStorage = $this->entityTypeManager->getStorage('helfi_hearings');

    $hearings = $entityStorage
      ->loadMultiple();

    return $hearings;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return Cache::mergeContexts(parent::getCacheContexts(), [
      'url.path',
      'languages:language_content',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return parent::getCacheTags();
  }

}
