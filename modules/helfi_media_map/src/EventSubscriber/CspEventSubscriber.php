<?php

declare(strict_types=1);

namespace Drupal\helfi_media_map\EventSubscriber;

use Drupal\csp\Event\PolicyAlterEvent;
use Drupal\helfi_media_map\Plugin\media\Source\Map;
use Drupal\helfi_platform_config\EventSubscriber\CspSubscriberBase;

/**
 * Event subscriber for CSP policy alteration.
 *
 * @package Drupal\helfi_media_map\EventSubscriber
 */
class CspEventSubscriber extends CspSubscriberBase {

  /**
   * Alter CSP policies.
   *
   * @param \Drupal\csp\Event\PolicyAlterEvent $event
   *   The policy alter event.
   */
  public function policyAlter(PolicyAlterEvent $event): void {
    $policy = $event->getPolicy();
    $policy->fallbackAwareAppendIfEnabled('frame-src', array_values(Map::VALID_URLS));
    $policy->fallbackAwareAppendIfEnabled('object-src', array_values(Map::VALID_URLS));
  }

}
