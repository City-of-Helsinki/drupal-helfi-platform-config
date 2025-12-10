<?php

declare(strict_types=1);

namespace Drupal\helfi_media_chart;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\helfi_media_chart\EventSubscriber\CspEventSubscriber;
use Drupal\helfi_platform_config\HelfiPlatformConfigServiceProvider;

/**
 * A service provider.
 */
final class HelfiMediaChartServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) : void {
    HelfiPlatformConfigServiceProvider::registerCspEventSubscribers($container, [
      CspEventSubscriber::class,
    ]);
  }

}
