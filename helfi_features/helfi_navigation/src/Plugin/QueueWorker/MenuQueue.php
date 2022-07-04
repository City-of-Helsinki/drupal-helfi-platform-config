<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;

/**
 * Processes menu sync tasks.
 *
 * @QueueWorker(
 *  id = "menu_queue",
 *  title = @Translation("Queue worker for menu synchronization"),
 *  cron = {"time" = 15}
 * )
 */
class MenuQueue extends QueueWorkerBase {

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
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->menuUpdater = \Drupal::service('helfi_navigation.menu_updater');
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $this->menuUpdater->setLangcode($data);
    $this->menuUpdater->syncMenu();
  }

}
