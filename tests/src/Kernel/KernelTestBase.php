<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\KernelTests\KernelTestBase as CoreKernelTestBase;

/**
 * Base class for kernel tests that use helfi_platform_config.
 */
abstract class KernelTestBase extends CoreKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_api_base',
    'helfi_platform_config',
    'config_rewrite',
    'diff',
  ];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);

    // RouteUpdateSubscriber calls getAllRoutes() on RoutingEvents::FINISHED.
    // Kernel tests wrap the route provider so the first getAllRoutes() starts
    // another rebuild, which fatal if a rebuild is already in progress
    // (e.g. ModuleInstaller). https://www.drupal.org/project/external_entities/issues/3549828
    if ($container->hasDefinition('external_entities.route_update_subscriber')) {
      $container->removeDefinition('external_entities.route_update_subscriber');
    }
  }

}