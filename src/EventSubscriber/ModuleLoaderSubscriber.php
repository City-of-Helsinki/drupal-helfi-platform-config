<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\EventSubscriber;

use Drupal\Core\DrupalKernelInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Loads module files after the container is rebuilt mid-request.
 *
 * The kernel loads module files only after the container has been
 * initialized, but LanguageRequestSubscriber may invoke hooks (such as
 * hook_entity_type_build()) during the container initialization event. If a
 * module was just installed, its procedural hook implementations are not yet
 * callable, which causes a fatal error, e.g. when installing modules from
 * update hooks with 'drush updb'.
 */
final readonly class ModuleLoaderSubscriber implements EventSubscriberInterface {

  public function __construct(
    private ModuleHandlerInterface $moduleHandler,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      // Must run before LanguageRequestSubscriber (priority 255).
      DrupalKernelInterface::CONTAINER_INITIALIZE_SUBREQUEST_FINISHED => ['onContainerInitialize', 1000],
    ];
  }

  /**
   * Loads all enabled module files.
   */
  public function onContainerInitialize(): void {
    $this->moduleHandler->loadAll();
  }

}
