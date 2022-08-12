<?php

declare(strict_types=1);

namespace Drupal\helfi_navigation\Plugin\Block;

use Drupal\system\Plugin\Block\SystemMenuBlock;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for all menu blocks.
 */
abstract class MenuBlockBase extends SystemMenuBlock {

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) : static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->logger = $container->get('logger.channel.helfi_navigation');
    return $instance;
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
   * {@inheritdoc}
   */
  public function getStartingLevel(): int {
    return (int) $this->getConfiguration()['level'] ?: 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getExpandAllItems(): bool {
    return $this->getConfiguration()['expand_all_items'] ?: FALSE;
  }

}
