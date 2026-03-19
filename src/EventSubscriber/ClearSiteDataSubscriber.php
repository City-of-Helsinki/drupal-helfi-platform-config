<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Component\Datetime\TimeInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Response subscriber listener.
 */
class ClearSiteDataSubscriber implements EventSubscriberInterface {

  // Valid directives for the Clear-Site-Data header.
  // @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Clear-Site-Data#directives
  const VALID_DIRECTIVES = [
    'cache',
    'clientHints',
    'cookies',
    'executionContexts',
    'prefetchCache',
    'prerenderCache',
    'storage',
    '*',
  ];

  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly TimeInterface $time,
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
   * To enable the header, set a configuration like this:
   *
   * @code
   * helfi_platform_config.clear_site_data:
   *   enable: true
   *   expire_after: 1773917159
   *   directives: 'cache,cookies'
   * @endcode
   *
   * The expire_after time is a Unix timestamp.
   * The directives are a comma-separated list of directives.
   *
   * To enable with Drush, run:
   *
   * @code
   * drush config:set helfi_platform_config.clear_site_data enable true
   * drush config:set helfi_platform_config.clear_site_data expire_after 1773917159
   * drush config:set helfi_platform_config.clear_site_data directives 'cache,cookies'
   * @endcode
   *
   * If you want to set an expire after time 24 hours from now, you can use:
   *
   * @code
   * drush config:set helfi_platform_config.clear_site_data expire_after $(($(date +%s) + 86400))
   * @endcode
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The event to respond to.
   */
  public function onResponse(ResponseEvent $event) : void {
    $config = $this->configFactory->get('helfi_platform_config.clear_site_data');
    $enable = $config->get('enable');
    $expire_after = $config->get('expire_after');
    $request_time = $this->time->getRequestTime();

    // If clear site data is not enabled or the expire after time is in the
    // past, return early.
    if (!$enable || ($expire_after && $expire_after < $request_time)) {
      return;
    }

    $directives = $config->get('directives') ?? '';
    $directives = explode(',', $directives);
    $directives = array_filter($directives, fn($directive) => in_array(trim($directive), self::VALID_DIRECTIVES));
    if (empty($directives)) {
      return;
    }

    // If the * directive is present, no need for other directives.
    if (in_array('*', $directives)) {
      $directives = ['*'];
    }

    // All directives must comply with the quoted-string grammar.
    // @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Clear-Site-Data#directives
    $directives = array_map(fn($directive) => sprintf('"%s"', $directive), $directives);

    $response = $event->getResponse();
    $response->headers->set('Clear-Site-Data', $directives);
    $event->setResponse($response);
  }

}
