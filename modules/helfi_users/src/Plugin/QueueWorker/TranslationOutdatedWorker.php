<?php

declare(strict_types=1);

namespace Drupal\helfi_users\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\helfi_users\TranslationOutdatedManager;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @QueueWorker(
 *   id = "translation_outdated_check",
 *   title = @Translation("Translation outdated check"),
 *   cron = {"time" = 30}
 * )
 */
class TranslationOutdatedWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  private EntityTypeManagerInterface $entityTypeManager;
  private TranslationOutdatedManager $translationOutdatedManager;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    $instance = new self($configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get(EntityTypeManagerInterface::class);
    $instance->translationOutdatedManager = $container->get('helfi_users.translation_outdated_manager');
    return $instance;
  }

  public function processItem(mixed $data): void {
    $node = $this->entityTypeManager->getStorage('node')->load($data['nid']);
    if (!$node instanceof NodeInterface) {
      return;
    }
    $this->translationOutdatedManager->updateOutdatedFlags($node);
  }

}
