<?php

declare(strict_types=1);

namespace Drupal\helfi_csp;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\helfi_csp\Hook\CronHook;
use Symfony\Component\DependencyInjection\Reference;

/**
 * A service provider.
 */
final class HelfiCspServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) : void {
    $modules = $container->getParameter('container.modules');

    if (!is_array($modules) || !isset($modules['csp_log'])) {
      return;
    }

    // Register CspLogService only if csp_log module is enabled.
    $container->register(CspLogService::class, CspLogService::class)
      ->setAutowired(TRUE)
      ->setAutoconfigured(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    $modules = $container->getParameter('container.modules');

    if (!is_array($modules) || !isset($modules['csp_log'])) {
      return;
    }

    $container->getDefinition(CronHook::class)
      ->addMethodCall('setCspLogService', [new Reference('csp_log')]);
  }

}
