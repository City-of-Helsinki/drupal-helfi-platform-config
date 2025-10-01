<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
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
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   */
  public function __construct(
    private readonly EnvironmentResolverInterface $environmentResolver,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly ModuleHandlerInterface $moduleHandler,
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

    // Block 'ibm_chat_app'.
    $policy->fallbackAwareAppendIfEnabled('connect-src', [
      'https://coh-chat-app-prod.ow6i4n9pdzm.eu-de.codeengine.appdomain.cloud',
      'https://coh-chat-app-test.mo1wrhhyog0.eu-de.codeengine.appdomain.cloud',
    ]);
    $policy->fallbackAwareAppendIfEnabled('font-src', [
      'https://coh-chat-app-prod.ow6i4n9pdzm.eu-de.codeengine.appdomain.cloud',
      'https://coh-chat-app-test.mo1wrhhyog0.eu-de.codeengine.appdomain.cloud',
    ]);
    $policy->fallbackAwareAppendIfEnabled('frame-src', [
      'https://coh-chat-app-prod.ow6i4n9pdzm.eu-de.codeengine.appdomain.cloud',
      'https://coh-chat-app-test.mo1wrhhyog0.eu-de.codeengine.appdomain.cloud',
      'https://coh-chat-app-ibm.eu-de.mybluemix.net',
      'https://coh-chat-app-prod-ibm.eu-de.mybluemix.net',
      'https://coh-chat-app-test.eu-de.mybluemix.net',
      'https://coh-chat-app-dev.eu-de.mybluemix.net',
      'https://coh-chat-app-prod.eu-de.mybluemix.net',
    ]);
    $policy->fallbackAwareAppendIfEnabled('img-src', [
      'https://coh-chat-app-prod.ow6i4n9pdzm.eu-de.codeengine.appdomain.cloud',
      'https://coh-chat-app-test.mo1wrhhyog0.eu-de.codeengine.appdomain.cloud',
    ]);
    $policy->fallbackAwareAppendIfEnabled('script-src', [
      'https://coh-chat-app-prod.ow6i4n9pdzm.eu-de.codeengine.appdomain.cloud',
      'https://coh-chat-app-test.mo1wrhhyog0.eu-de.codeengine.appdomain.cloud',
    ]);
    $policy->fallbackAwareAppendIfEnabled('style-src', [
      'https://coh-chat-app-prod.ow6i4n9pdzm.eu-de.codeengine.appdomain.cloud',
      'https://coh-chat-app-test.mo1wrhhyog0.eu-de.codeengine.appdomain.cloud',
    ]);

    // Block 'react_and_share'.
    $policy->fallbackAwareAppendIfEnabled('connect-src', [
      'https://*.reactandshare.com',
      'https://*.askem.com',
    ]);
    $policy->fallbackAwareAppendIfEnabled('img-src', [
      'https://*.reactandshare.com',
    ]);
    $policy->fallbackAwareAppendIfEnabled('script-src', [
      'https://*.reactandshare.com',
      'https://*.askem.com',
    ]);

    // Block 'telia_ace_widget'.
    $policy->fallbackAwareAppendIfEnabled('connect-src', [
      'https://hel.humany.net',
      'https://wds.ace.teliacompany.com',
      'https://chat.ace.teliacompany.net',
      'https://api.ace.teliacompany.net',
    ]);
    $policy->fallbackAwareAppendIfEnabled('font-src', [
      'https://hel.humany.net',
      'https://ace-knowledge-cdn.teliacompany.net',
      'https://makasiini.hel.ninja',
    ]);
    $policy->fallbackAwareAppendIfEnabled('frame-src', [
      'https://wds.ace.teliacompany.com',
    ]);
    $policy->fallbackAwareAppendIfEnabled('script-src', [
      'https://wds.ace.teliacompany.com',
    ]);
    $policy->fallbackAwareAppendIfEnabled('style-src', [
      'https://hel.humany.net',
      'https://wds.ace.teliacompany.com',
    ]);

    // Allow access to Elasticsearch proxy.
    $proxy_url = $this->configFactory->get('elastic_proxy.settings')?->get('elastic_proxy_url');
    if ($proxy_url) {
      $policy->fallbackAwareAppendIfEnabled('connect-src', [$proxy_url]);
    }

    // Allow access to all hel.fi subdomains.
    $policy->fallbackAwareAppendIfEnabled('connect-src', ['https://*.hel.fi']);
    $policy->fallbackAwareAppendIfEnabled('font-src', ['https://*.hel.fi']);
    $policy->fallbackAwareAppendIfEnabled('frame-src', ['https://*.hel.fi']);
    $policy->fallbackAwareAppendIfEnabled('script-src', ['https://*.hel.fi']);
    $policy->fallbackAwareAppendIfEnabled('style-src', ['https://*.hel.fi']);

    // Siteimprove-module.
    if ($this->moduleHandler->moduleExists('siteimprove')) {
      $policy->fallbackAwareAppendIfEnabled('connect-src', ['https://*.siteimprove.com']);
      $policy->fallbackAwareAppendIfEnabled('frame-src', [
        'https://*.siteimprove.com',
        'https://*.siteimproveanalytics.com',
      ]);
    }

    // "Social media"-module.
    if ($this->moduleHandler->moduleExists('social_media')) {
      $policy->fallbackAwareAppendIfEnabled('connect-src', ['https://connect.facebook.net']);
      $policy->fallbackAwareAppendIfEnabled('script-src', ['https://connect.facebook.net']);
    }

    // Common rules that affect many modules and theme layers.
    $policy->fallbackAwareAppendIfEnabled('img-src', ['data:']);
    $policy->fallbackAwareAppendIfEnabled('media-src', ['data:']);

    // Miscellaneous frame-src domains migrated from helfi proxy.
    $policy->fallbackAwareAppendIfEnabled('frame-src', [
      'https://*.userneeds.com',
      'https://agreeable-island-03e85b803.azurestaticapps.net',
      'https://*.hotjar.com',
      'https://*.facebook.com',
      'https://*.twitter.com',
      'https://*.linkedin.com',
      'https://*.readspeaker.com',
      'https://*.google.com',
      'https://*.snoobi.com',
      'https://*.dreambroker.com',
      'https://dreambroker.com',
      'https://pollev.com',
      'https://tyoterveys-helsinki-pv.mail-eur.net',
      'https://walls.io',
      'https://*.flockler.com',
      'https://*.lightwidget.com',
      'https://hel-thk-botti.kuurahealth.com',
      'https://*.giosg.com',
      'https://*.giosgusercontent.com',
      'https://helfi.fi1.frosmo.com',
      'https://survey.feedbackly.com',
      'https://hkp.maanmittauslaitos.fi',
      'https://reittiopas.hsl.fi',
    ]);

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
        $policy->fallbackAwareAppendIfEnabled('script-src', $environment->getBaseUrl());
        $policy->fallbackAwareAppendIfEnabled('style-src', $environment->getBaseUrl());
        $policy->fallbackAwareAppendIfEnabled('connect-src', $environment->getBaseUrl());
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
