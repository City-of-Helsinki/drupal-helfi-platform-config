<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_paragraphs_news_list\Kernel;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Render\HtmlResponse;
use Drupal\Core\Session\AccountInterface;
use Drupal\helfi_paragraphs_news_list\EventSubscriber\CacheResponseSubscriber;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Tests the CacheResponseSubscriber.
 */
class CacheResponseSubscriberTest extends KernelTestBase {

  /**
   * Tests the handling of empty news list cache.
   */
  public function testEmptyNewsListCacheHandling(): void {
    $time = $this->prophesize(TimeInterface::class);
    $time->getRequestTime()->willReturn(1234567890);

    $account = $this->prophesize(AccountInterface::class);
    $account->isAuthenticated()->willReturn(FALSE);

    $cacheableMetadata = $this->prophesize(CacheableMetadata::class);
    $cacheableMetadata->getCacheTags()
      ->willReturn(['helfi_news_list_empty_results', 'node:123']);

    $response = $this->prophesize(HtmlResponse::class);
    $response->getCacheableMetadata()
      ->willReturn($cacheableMetadata->reveal());

    $response->setMaxAge(CacheResponseSubscriber::EMPTY_LIST_MAX_AGE)
      ->willReturn($response)
      ->shouldBeCalled();

    $response->setExpires(new \DateTime('@' . (1234567890 + CacheResponseSubscriber::EMPTY_LIST_MAX_AGE)))
      ->willReturn($response)
      ->shouldBeCalled();

    $event = $this->prophesize(ResponseEvent::class);
    $event->getResponse()->willReturn($response->reveal());

    $sut = new CacheResponseSubscriber(
      $time->reveal(),
      $account->reveal(),
    );

    $sut->onKernelResponse($event->reveal());
  }

  /**
   * Tests the handling of non-empty news list cache.
   */
  public function testNonEmptyNewsListCacheHandling(): void {
    $time = $this->prophesize(TimeInterface::class);
    $time->getRequestTime()->willReturn(1234567890);

    $account = $this->prophesize(AccountInterface::class);
    $account->isAuthenticated()->willReturn(FALSE);

    $cacheableMetadata = $this->prophesize(CacheableMetadata::class);
    $cacheableMetadata->getCacheTags()
      ->willReturn(['node:123']);

    $response = $this->prophesize(HtmlResponse::class);
    $response->getCacheableMetadata()
      ->willReturn($cacheableMetadata->reveal());

    $response->setMaxAge(CacheResponseSubscriber::EMPTY_LIST_MAX_AGE)
      ->shouldNotBeCalled();

    $response->setExpires(new \DateTime('@' . (1234567890 + CacheResponseSubscriber::EMPTY_LIST_MAX_AGE)))
      ->shouldNotBeCalled();

    $event = $this->prophesize(ResponseEvent::class);
    $event->getResponse()->willReturn($response->reveal());

    $sut = new CacheResponseSubscriber(
      $time->reveal(),
      $account->reveal(),
    );

    $sut->onKernelResponse($event->reveal());
  }

}
