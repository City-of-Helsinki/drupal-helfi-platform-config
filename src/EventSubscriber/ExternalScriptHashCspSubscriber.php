<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\csp\Csp;
use Drupal\csp\CspEvents;
use Drupal\csp\Event\PolicyAlterEvent;
use Drupal\helfi_platform_config\Asset\JsCspHashCollector;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adds CSP hashes for external scripts loaded with SRI integrity metadata.
 *
 * Hashes are appended directly to the policy because PolicyHelper::appendHash()
 * will not add hashes when 'unsafe-inline' is already enabled on script-src.
 *
 * @see https://www.w3.org/TR/CSP3/#external-hash
 */
class ExternalScriptHashCspSubscriber implements EventSubscriberInterface {

  /**
   * Constructs an ExternalScriptHashCspSubscriber.
   */
  public function __construct(
    private JsCspHashCollector $hashCollector,
    private ConfigFactoryInterface $configFactory,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    if (!class_exists(CspEvents::class)) {
      return [];
    }

    $events[CspEvents::POLICY_ALTER][] = ['policyAlter', -16];

    return $events;
  }

  /**
   * Append collected script hashes to the CSP policy.
   */
  public function policyAlter(PolicyAlterEvent $event): void {
    if (!$this->isEnabled()) {
      return;
    }

    $policy = $event->getPolicy();

    if ($this->isStrictDynamicEnabled()) {
      $policy->fallbackAwareAppendIfEnabled('script-src', [Csp::POLICY_STRICT_DYNAMIC]);
      $policy->fallbackAwareAppendIfEnabled('script-src-elem', [Csp::POLICY_STRICT_DYNAMIC]);
    }

    $hashes = $this->hashCollector->getHashes()['script-src-elem'] ?? [];
    if ($hashes === []) {
      return;
    }

    foreach (array_keys($hashes) as $hash) {
      $quoted_hash = "'" . trim($hash, "'") . "'";
      $policy->fallbackAwareAppendIfEnabled('script-src-elem', [$quoted_hash]);
      $policy->fallbackAwareAppendIfEnabled('script-src', [$quoted_hash]);
    }
  }

  /**
   * Check if external script hashes are enabled.
   */
  private function isEnabled(): bool {
    return (bool) $this->configFactory
      ->get('helfi_platform_config.csp')
      ->get('external_script_hashes');
  }

  /**
   * Check if strict-dynamic should be added to script-src.
   */
  private function isStrictDynamicEnabled(): bool {
    return (bool) $this->configFactory
      ->get('helfi_platform_config.csp')
      ->get('strict_dynamic');
  }

}
