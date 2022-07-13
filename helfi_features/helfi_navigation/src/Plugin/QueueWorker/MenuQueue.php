<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\helfi_navigation\MenuUpdater;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Processes menu sync tasks.
 *
 * @QueueWorker(
 *  id = "helfi_navigation_menu_queue",
 *  title = @Translation("Queue worker for menu synchronization"),
 *  cron = {"time" = 15}
 * )
 */
class MenuQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {
  use StringTranslationTrait;

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
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger channel.
   * @param \Drupal\helfi_navigation\MenuUpdater $menu_updater
   *   The Menu updater service.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected LoggerInterface $logger,
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
      $container->get('logger.channel.helfi_navigation'),
      $container->get('helfi_navigation.menu_updater'),
    );
  }

  /**
   * Process queue item.
   *
   * @param object $data
   *   Data of the processable menu / menu item.
   *
   * @throws \Exception
   *   Throws exception if language code is not set.
   */
  public function processItem($data) {
    $message = $this->t('Global menu queue triggered with: @eid, id: @id, label: @label', [
      '@eid' => $data->getEntityTypeId(),
      '@id' => $data->id(),
      '@label' => $data->label(),
    ]);
    $this->logger->info($message);
    $this->menuUpdater->syncMenu();
  }

}
