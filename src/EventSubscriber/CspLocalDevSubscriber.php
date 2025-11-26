<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\EventSubscriber;

use Drupal\csp\Event\PolicyAlterEvent;
use Drupal\helfi_api_base\Environment\Environment;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\Project;

/**
 * Event subscriber for CSP policy alteration.
 *
 * CSP directives for local dev environments.
 *
 * @package Drupal\helfi_platform_config\EventSubscriber
 */
class CspLocalDevSubscriber extends CspSubscriberBase {

  /**
   * Alter CSP policies.
   *
   * @param \Drupal\csp\Event\PolicyAlterEvent $event
   *   The policy alter event.
   */
  public function policyAlter(PolicyAlterEvent $event): void {
    // Add frontpage domain when on local dev environments to allow
    // other core instances to fetch frontpage assets. All core instances
    // share the same domain in testing and production environments, so CSP
    // value 'self' is sufficient there, but on local dev environments the
    // domains are different, so frontpage domain needs to be added to allow
    // proper behavior for things like the cookie banner.
    try {
      $environmentResolver = $this->handlers->get('environmentResolver');
      $current_site = $environmentResolver->getActiveProject();

      if ($current_site instanceof Project && $current_site->getName() !== Project::ETUSIVU) {
        $environment = $environmentResolver->getEnvironment(
          Project::ETUSIVU,
          $environmentResolver->getActiveEnvironmentName()
        );

        if ($environment instanceof Environment && $environment->getEnvironment() === EnvironmentEnum::Local) {
          $policy = $event->getPolicy();

          $policy->fallbackAwareAppendIfEnabled('connect-src', $environment->getBaseUrl());
          $policy->fallbackAwareAppendIfEnabled('script-src', $environment->getBaseUrl());
          $policy->fallbackAwareAppendIfEnabled('style-src', $environment->getBaseUrl());
        }
      }
    }
    catch (\InvalidArgumentException) {
    }
  }

}
