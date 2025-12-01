<?php

declare(strict_types=1);

namespace Drupal\helfi_media_chart;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\helfi_media_chart\EventSubscriber\CspEventSubscriber;
use Symfony\Component\DependencyInjection\Reference;

/**
 * A service provider.
 */
final class HelfiMediaChartServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) : void {
    // We cannot use the module handler as the container is not yet compiled.
    // @see \Drupal\Core\DrupalKernel::compileContainer()
    $modules = $container->getParameter('container.modules');

    // Register CSP event subscribers only if the csp module is enabled.
    if (isset($modules['csp'])) {
      $event_subscribers = [
        CspEventSubscriber::class,
      ];

      foreach ($event_subscribers as $event_subscriber) {
        $container->register($event_subscriber, $event_subscriber)
          ->addTag('event_subscriber')
          ->addArgument(new Reference('config.factory'))
          ->addArgument(new Reference('module_handler'))
          ->addArgument(new Reference('helfi_api_base.environment_resolver'))
          ->addArgument(new Reference('csp.policy_helper'));
      }
    }
  }

}
