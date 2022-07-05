<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\helfi_navigation\MenuUpdater;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes menu sync tasks.
 *
 * @QueueWorker(
 *  id = "menu_queue",
 *  title = @Translation("Queue worker for menu synchronization"),
 *  cron = {"time" = 15}
 * )
 */
class MenuQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Menu updater.
   *
   * @var \Drupal\helfi_navigation\MenuUpdater
   */
  protected mixed $menuUpdater;

  /**
   * Constructs a new MenuQueue.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\helfi_navigation\MenuUpdater $menu_updater
   *   The Menu updater service.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    MenuUpdater $menu_updater
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->menuUpdater = $menu_updater;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('helfi_navigation.menu_updater'),
    );
  }

  /**
   * Process queue item.
   *
   * @param $lang_code
   *   Data of the processable menu / menu item.
   *
   * @throws \Exception
   *   Throws exception if language code is not set.
   */
  public function processItem($lang_code) {
    $this->menuUpdater->syncMenu($lang_code);
  }

}
