<?php

declare(strict_types=1);

namespace Drupal\helfi_csp\EventSubscriber;

use Drupal\csp\CspEvents;
use Drupal\csp\Event\PolicyAlterEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for CSP policy alteration.
 *
 * @package Drupal\helfi_csp\EventSubscriber
 */
class CspEventSubscriber implements EventSubscriberInterface {

  const BAD_DIRECTIVE_VALUES = [
    // Drupal module select2 does some library path altering, which results in
    // 'dist' being detected as an external domain.
    'dist',
  ];

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      CspEvents::POLICY_ALTER => 'policyAlter',
    ];
  }

  /**
   * Alter CSP policies.
   *
   * @param \Drupal\csp\Event\PolicyAlterEvent $event
   *   The policy alter event.
   */
  public function policyAlter(PolicyAlterEvent $event): void {
    $this->cleanDirectiveValues($event, ['script-src', 'style-src']);
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
