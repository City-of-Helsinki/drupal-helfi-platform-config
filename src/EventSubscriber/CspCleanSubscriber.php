<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\EventSubscriber;

use Drupal\csp\Event\PolicyAlterEvent;
use Drupal\csp\CspEvents;

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
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = [];

    if (class_exists(CspEvents::class)) {
      // Run after other CSP policy alter subscribers.
      $events[CspEvents::POLICY_ALTER] = ['policyAlter', -100];
    }

    return $events;
  }

  /**
   * Alter CSP policies.
   *
   * @param \Drupal\csp\Event\PolicyAlterEvent $event
   *   The policy alter event.
   */
  public function policyAlter(PolicyAlterEvent $event): void {
    // Some directives are auto added even if disabled in config.
    // Let's make sure config is respected here.
    $this->removeDisallowedDirectives($event, [
      'script-src-elem',
      'style-src-elem',
      'script-src-attr',
      'style-src-attr',
    ]);

    // Clean up bad directive values.
    $this->cleanDirectiveValues($event, [
      'script-src',
      'script-src-elem',
      'style-src',
      'style-src-elem',
    ]);
  }

  /**
   * Remove directives that are not allowed by the CSP policy.
   *
   * @param \Drupal\csp\Event\PolicyAlterEvent $event
   *   The policy alter event.
   * @param string[] $directives
   *   The directives to remove.
   */
  protected function removeDisallowedDirectives(PolicyAlterEvent $event, array $directives): void {
    $policy = $event->getPolicy();
    $cspConfig = $this->configFactory->get('csp.settings');
    foreach ($directives as $directive) {
      if (!$cspConfig->get($directive) && $policy->hasDirective($directive)) {
        $policy->removeDirective($directive);
      }
    }
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
