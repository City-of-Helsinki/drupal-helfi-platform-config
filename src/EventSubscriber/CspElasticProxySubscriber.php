<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\EventSubscriber;

use Drupal\csp\Event\PolicyAlterEvent;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_api_base\Environment\ProjectRoleEnum;
use Drupal\helfi_api_base\Environment\ServiceEnum;

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
    $urls = [];

    $proxy_url = $this->configFactory->get('elastic_proxy.settings')?->get('elastic_proxy_url');
    if ($proxy_url) {
      $urls = [$proxy_url];
    }

    // Core sites should have access to etusivu
    // elasticsearch for shared functionality.
    try {
      $project = $this->environmentResolver->getActiveProject();
      if ($project->hasRole(ProjectRoleEnum::Core)) {
        $environment = $this->environmentResolver->getEnvironment(Project::ETUSIVU, $this->environmentResolver->getActiveEnvironmentName());
        $urls[] = $environment->getService(ServiceEnum::PublicElasticProxy)->address->getAddress();
      }
    }
    catch (\InvalidArgumentException) {
    }

    if ($urls) {
      $policy->fallbackAwareAppendIfEnabled('connect-src', array_unique($urls));
    }
  }

}
