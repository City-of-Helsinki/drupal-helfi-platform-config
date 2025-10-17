<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\EventSubscriber;

use Drupal\csp\Event\PolicyAlterEvent;

/**
 * Event subscriber for CSP policy alteration.
 *
 * CSP directives for Sentry logging.
 *
 * @package Drupal\helfi_platform_config\EventSubscriber
 */
class CspSentrySubscriber extends CspSubscriberBase {

  /**
   * Alter CSP policies.
   *
   * @param \Drupal\csp\Event\PolicyAlterEvent $event
   *   The policy alter event.
   */
  public function policyAlter(PolicyAlterEvent $event): void {
    // Sentry DSN for javascript error reporting is defined in
    // SENTRY_DSN_PUBLIC environment variable. Parse the url from the DSN and
    // add it to the CSP policy.
    if ($sentryDsn = getenv('SENTRY_DSN_PUBLIC')) {
      $sentryUrlParsed = parse_url($sentryDsn);
      $sentryUrl = isset($sentryUrlParsed['scheme']) && isset($sentryUrlParsed['host']) ? sprintf('%s://%s', $sentryUrlParsed['scheme'], $sentryUrlParsed['host']) : '';

      // If the url is not valid, return.
      if (!$sentryUrl) {
        return;
      }

      // Add the url to the connect-src directive.
      $policy = $event->getPolicy();
      $policy->fallbackAwareAppendIfEnabled('connect-src', [$sentryUrl]);
    }
  }

}
