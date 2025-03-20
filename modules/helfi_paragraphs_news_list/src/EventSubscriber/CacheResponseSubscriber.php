<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_news_list\EventSubscriber;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\Core\Render\HtmlResponse;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Cache response subscriber.
 */
final class CacheResponseSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a new CacheResponseSubscriber object.
   *
   * @param \Drupal\Core\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Session\UserInterface $currentUser
   *   The current user.
   */
  public function __construct(
    private readonly TimeInterface $time,
    private readonly AccountInterface $currentUser,
  ) {}

  /**
   * Handle news list cache.
   *
   * @param \Drupal\Core\Render\HtmlResponse $response
   *   The response.
   */
  protected function handleNewsListCache(HtmlResponse $response): void {
    $cache_tags = $response->getCacheableMetadata()->getCacheTags();

    // Set max-age to 10 minutes for pages with empty news list results.
    if (in_array('helfi_news_list_empty_results', $cache_tags)) {
      $max_age = 600;
      $response->setMaxAge($max_age);
      $date = new \DateTime('@' . ($this->time->getRequestTime() + $max_age));
      $response->setExpires($date);
    }
  }

  /**
   * Kernel response event handler.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The response event.
   */
  public function onKernelResponse(ResponseEvent $event): void {
    $response = $event->getResponse();

    // Only handle cacheable responses.
    if (!$response instanceof CacheableResponseInterface) {
      return;
    }

    // Only handle anonymous user requests.
    if ($this->currentUser->isAuthenticated()) {
      return;
    }

    $this->handleNewsListCache($response);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::RESPONSE => ['onKernelResponse'],
    ];
  }

}
