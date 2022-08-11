<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\helfi_navigation\MenuUpdater;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\system\MenuInterface;
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
   * The menu updater service.
   *
   * @var \Drupal\helfi_navigation\MenuUpdater
   */
  private MenuUpdater $menuUpdater;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->menuUpdater = $container->get('helfi_navigation.menu_updater');
    return $instance;
  }

  /**
   * Process queue item.
   *
   * @param string $data
   *   Data of the processable language code.
   *
   * @throws \Exception
   *   Throws exception if language code is not set.
   */
  public function processItem($data) {
    if (!is_string($data)) {
      return;
    }
    $this->menuUpdater->syncMenu($data);
  }

}
