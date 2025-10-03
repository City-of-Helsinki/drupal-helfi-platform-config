<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\EventSubscriber;

use Drupal\csp\Event\PolicyAlterEvent;

/**
 * Event subscriber for CSP policy alteration.
 *
 * CSP directives for Elasticsearch proxy.
 *
 * @package Drupal\helfi_platform_config\EventSubscriber
 */
class CspElasticProxySubscriber extends CspSubscriberBase {

  /**
   * Alter CSP policies.
   *
   * @param \Drupal\csp\Event\PolicyAlterEvent $event
   *   The policy alter event.
   */
  public function policyAlter(PolicyAlterEvent $event): void {
    $policy = $event->getPolicy();
    $proxy_url = $this->configFactory->get('elastic_proxy.settings')?->get('elastic_proxy_url');
    if ($proxy_url) {
      $policy->fallbackAwareAppendIfEnabled('connect-src', [$proxy_url]);
    }
  }

}
