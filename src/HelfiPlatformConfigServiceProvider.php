<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\helfi_platform_config\ConfigUpdate\ConfigRewriter;

/**
 * A service provider.
 */
final class HelfiPlatformConfigServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) : void {
    // @todo Remove this when https://www.drupal.org/project/config_rewrite/issues/3152228
    // is fixed.
    if ($container->hasDefinition('config_rewrite.config_rewriter')) {
      $definition = $container->getDefinition('config_rewrite.config_rewriter');
      $definition->setClass(ConfigRewriter::class);
    }
  }

}
