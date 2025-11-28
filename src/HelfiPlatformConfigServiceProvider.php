<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\helfi_platform_config\ConfigUpdate\ConfigRewriter;
use Drupal\helfi_platform_config\EventSubscriber\CspCleanSubscriber;
use Drupal\helfi_platform_config\EventSubscriber\CspIbmChatAppSubscriber;
use Drupal\helfi_platform_config\EventSubscriber\CspReactAndShareSubscriber;
use Drupal\helfi_platform_config\EventSubscriber\CspTeliaAceWidgetSubscriber;
use Drupal\helfi_platform_config\EventSubscriber\CspElasticProxySubscriber;
use Drupal\helfi_platform_config\EventSubscriber\CspCommonSubscriber;
use Drupal\helfi_platform_config\EventSubscriber\CspLocalDevSubscriber;
use Drupal\helfi_platform_config\EventSubscriber\CspSiteimproveSubscriber;
use Drupal\helfi_platform_config\EventSubscriber\CspSentrySubscriber;
use Drupal\helfi_platform_config\EventSubscriber\CspSocialMediaSubscriber;
use Drupal\helfi_platform_config\SearchAPI\Query\QueryResultParser;
use Symfony\Component\DependencyInjection\Reference;

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

    // Replace the default Elasticsearch Connector query result parser with
    // our own.
    if ($container->hasDefinition('elasticsearch_connector.query_result_parser')) {
      $definition = $container->getDefinition('elasticsearch_connector.query_result_parser');
      $definition->setClass(QueryResultParser::class);
      $definition->addArgument(new Reference(MultisiteSearch::class));
    }
  }

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
        CspCleanSubscriber::class,
        CspIbmChatAppSubscriber::class,
        CspReactAndShareSubscriber::class,
        CspTeliaAceWidgetSubscriber::class,
        CspElasticProxySubscriber::class,
        CspCommonSubscriber::class,
        CspLocalDevSubscriber::class,
        CspSiteimproveSubscriber::class,
        CspSentrySubscriber::class,
        CspSocialMediaSubscriber::class,
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
