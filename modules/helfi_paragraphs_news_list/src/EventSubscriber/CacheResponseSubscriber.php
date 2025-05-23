<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_news_list\EventSubscriber;

use Drupal\Component\Datetime\TimeInterface;
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
   * The max age to cache empty news list results.
   */
  public const EMPTY_LIST_MAX_AGE = 600;

  /**
   * Constructs a new CacheResponseSubscriber object.
   *
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   */
  public function __construct(
    private readonly TimeInterface $time,
    private readonly AccountInterface $currentUser,
  ) {}

  /**
   * Handle news list cache.
   *
   * Sets the max-age and expires header for pages with empty news list results.
   *
   * @param \Drupal\Core\Render\HtmlResponse $response
   *   The response.
   */
  protected function handleNewsListCache(HtmlResponse $response): void {
    $cache_tags = $response->getCacheableMetadata()->getCacheTags();

    // Set max-age to 10 minutes for pages with empty news list results.
    if (in_array('helfi_news_list_empty_results', $cache_tags)) {
      $response->setMaxAge(self::EMPTY_LIST_MAX_AGE);
      $date = new \DateTime('@' . ($this->time->getRequestTime() + self::EMPTY_LIST_MAX_AGE));
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
    if (!$response instanceof HtmlResponse) {
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
