<?php

declare(strict_types=1);

namespace Drupal\helfi_csp;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * A service provider.
 */
final class HelfiCspServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) : void {
    $modules = $container->getParameter('container.modules');

    if (!isset($modules['csp_log'])) {
      return;
    }

    // Register CspLogService only if csp_log module is enabled.
    $container->register(CspLogService::class, CspLogService::class)
      ->setAutowired(TRUE)
      ->setAutoconfigured(TRUE);
  }

}
