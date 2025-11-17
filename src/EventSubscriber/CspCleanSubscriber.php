<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\EventSubscriber;

use Drupal\csp\Event\PolicyAlterEvent;

/**
 * Event subscriber for CSP policy alteration.
 *
 * Removes bad values and disabled directives.
 *
 * @package Drupal\helfi_platform_config\EventSubscriber
 */
class CspCleanSubscriber extends CspSubscriberBase {

  const BAD_DIRECTIVE_VALUES = [
    // Drupal module select2 does library path altering, which in some cases
    // results in 'dist' being detected as an external domain.
    'dist',
  ];

  /**
   * Alter CSP policies.
   *
   * @param \Drupal\csp\Event\PolicyAlterEvent $event
   *   The policy alter event.
   */
  public function policyAlter(PolicyAlterEvent $event): void {
    $policy = $event->getPolicy();
    $cspConfig = $this->configFactory->get('csp.settings');

    // Some directives are auto added even if disabled in config.
    // Let's make sure config is respected here.
    if (!$cspConfig->get('script-src-elem') && $policy->hasDirective('script-src-elem')) {
      $policy->removeDirective('script-src-elem');
    }
    if (!$cspConfig->get('style-src-elem') && $policy->hasDirective('style-src-elem')) {
      $policy->removeDirective('style-src-elem');
    }

    // Clean up bad directive values.
    $this->cleanDirectiveValues($event, [
      'script-src',
      'script-src-elem',
      'style-src',
      'style-src-elem',
    ]);
  }

  /**
   * Clean directive content from known bad values.
   *
   * @param \Drupal\csp\Event\PolicyAlterEvent $event
   *   The policy alter event.
   * @param string[] $directives
   *   The directives to clean.
   */
  protected function cleanDirectiveValues(PolicyAlterEvent $event, array $directives): void {
    $policy = $event->getPolicy();

    foreach ($directives as $directive) {
      if ($policy->hasDirective($directive)) {
        $values = array_filter(
          $policy->getDirective($directive),
          fn ($value) => !in_array($value, self::BAD_DIRECTIVE_VALUES),
        );
        $policy->setDirective($directive, $values);
      }
    }
  }

}
