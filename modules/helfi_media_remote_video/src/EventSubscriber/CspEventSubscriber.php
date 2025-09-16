<?php

declare(strict_types=1);

namespace Drupal\helfi_media_remote_video\EventSubscriber;

use Drupal\csp\CspEvents;
use Drupal\csp\Event\PolicyAlterEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for CSP policy alteration.
 *
 * @package Drupal\helfi_media_remote_video\EventSubscriber
 */
class CspEventSubscriber implements EventSubscriberInterface {

  const FRAME_SRC = [
    'https://*.youtube-nocookie.com',
    'https://youtube-nocookie.com',
    'https://*.youtube.com',
    'https://youtube.com',
    'https://*.youtu.be',
    'https://youtu.be',
    'https://*.vimeo.com',
    'https://vimeo.com',
    'https://*.icareus.com',
    'https://icareus.com',
    'https://*.helsinkikanava.fi',
  ];
  const CONNECT_SRC = [
    'https://*.youtube-nocookie.com',
    'https://youtube-nocookie.com',
  ];

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
    $policy->fallbackAwareAppendIfEnabled('connect-src', array_values(self::CONNECT_SRC));
    $policy->fallbackAwareAppendIfEnabled('frame-src', array_values(self::FRAME_SRC));
    $policy->fallbackAwareAppendIfEnabled('object-src', array_values(self::FRAME_SRC));
  }

}
