<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\EventSubscriber;

use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\helfi_platform_config\ClearSiteData;

/**
 * Response subscriber listener.
 */
class ClearSiteDataSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected readonly ClearSiteData $clearSiteData,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() : array {
    return [
      KernelEvents::RESPONSE => ['onResponse', -100],
    ];
  }

  /**
   * Adds Clear-Site-Data -header to response.
   *
   * To enable the header, run `drush helfi:clear-site-data:enable`
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The event to respond to.
   */
  public function onResponse(ResponseEvent $event) : void {
    $response = $event->getResponse();

    // Add cacheable dependency to allow purging when the config changes.
    if ($response instanceof CacheableResponseInterface) {
      $response->addCacheableDependency($this->clearSiteData->getDependencyMetadata());
      $event->setResponse($response);
    }

    if (!$this->clearSiteData->isEnabled()) {
      return;
    }

    // All directives must comply with the quoted-string grammar.
    // @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Clear-Site-Data#directives
    $directives = array_map(fn($directive) => sprintf('"%s"', $directive), $this->clearSiteData->getActiveDirectives());

    $response->headers->set('Clear-Site-Data', $directives);
    $event->setResponse($response);
  }

}
