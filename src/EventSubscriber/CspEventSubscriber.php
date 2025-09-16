<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\csp\CspEvents;
use Drupal\csp\Event\PolicyAlterEvent;
use Drupal\helfi_api_base\Environment\Environment;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_api_base\Environment\Project;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for CSP policy alteration.
 *
 * @package Drupal\helfi_platform_config\EventSubscriber
 */
class CspEventSubscriber implements EventSubscriberInterface {

  const BAD_DIRECTIVE_VALUES = [
    // Drupal module select2 does library path altering, which in some cases
    // results in 'dist' being detected as an external domain.
    'dist',
  ];

  /**
   * Constructor.
   *
   * @param \Drupal\helfi_api_base\Environment\EnvironmentResolverInterface $environmentResolver
   *   The environment resolver.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    private readonly EnvironmentResolverInterface $environmentResolver,
    private readonly ConfigFactoryInterface $configFactory,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = [];

    if (class_exists(CspEvents::class)) {
      $events[CspEvents::POLICY_ALTER] = 'policyAlter';
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
    // Clean up bad directive values.
    $this->cleanDirectiveValues($event, [
      'script-src',
      'script-src-elem',
      'style-src',
      'style-src-elem',
    ]);

    // Allow access to Elasticsearch proxy.
    $proxy_url = $this->configFactory->get('elastic_proxy.settings')?->get('elastic_proxy_url');
    if ($proxy_url) {
      $policy = $event->getPolicy();
      if ($policy->hasDirective('connect-src')) {
        $policy->appendDirective('connect-src', $proxy_url);
      }
    }

    // Add frontpage domain when on local dev environments to allow
    // other core instances to fetch frontpage assets. All core instances
    // share the same domain in testing and production environments, so CSP
    // value 'self' is sufficient there, but on local dev environments the
    // domains are different, so frontpage domain needs to be added to allow
    // proper behavior for things like the cookie banner.
    $current_site = NULL;
    try {
      $current_site = $this->environmentResolver->getActiveProject();
    }
    catch (\InvalidArgumentException) {
    }
    if ($current_site instanceof Project && $current_site->getName() !== Project::ETUSIVU) {
      $environment = $this->environmentResolver->getEnvironment(
        Project::ETUSIVU,
        $this->environmentResolver->getActiveEnvironmentName()
      );
      if ($environment instanceof Environment && $environment->getEnvironment() === EnvironmentEnum::Local) {
        $policy = $event->getPolicy();
        if ($policy->hasDirective('script-src-elem')) {
          $policy->appendDirective('script-src-elem', $environment->getBaseUrl());
        }
        if ($policy->hasDirective('style-src-elem')) {
          $policy->appendDirective('style-src-elem', $environment->getBaseUrl());
        }
        if ($policy->hasDirective('connect-src')) {
          $policy->appendDirective('connect-src', $environment->getBaseUrl());
        }
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
