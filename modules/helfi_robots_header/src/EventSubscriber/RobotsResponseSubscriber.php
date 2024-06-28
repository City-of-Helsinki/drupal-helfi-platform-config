<?php

declare(strict_types=1);

namespace Drupal\helfi_robots_header\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Adds an X-Robots-Tag to response headers.
 */
final class RobotsResponseSubscriber implements EventSubscriberInterface {

  public const X_ROBOTS_TAG_HEADER_NAME = 'DRUPAL_X_ROBOTS_TAG_HEADER';

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() : array {
    $events[KernelEvents::RESPONSE][] = ['onResponse', -100];
    return $events;
  }

  /**
   * Adds an X-Robots-Tag response header.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The event to respond to.
   */
  public function onResponse(ResponseEvent $event) : void {
    $response = $event->getResponse();

    if ((bool) getenv(self::X_ROBOTS_TAG_HEADER_NAME)) {
      $response->headers->add(['X-Robots-Tag' => 'noindex, nofollow']);
    }
  }

}
