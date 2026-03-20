<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\EventSubscriber;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\helfi_platform_config\ClearSiteData;
use Drupal\helfi_platform_config\EventSubscriber\ClearSiteDataSubscriber;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Unit tests for ClearSiteDataSubscriber.
 */
#[CoversClass(ClearSiteDataSubscriber::class)]
#[Group('helfi_platform_config')]
final class ClearSiteDataSubscriberTest extends UnitTestCase {

  /**
   * The ClearSiteData mock.
   */
  private ClearSiteData&MockObject $clearSiteData;

  /**
   * The system under test.
   */
  private ClearSiteDataSubscriber $sut;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->clearSiteData = $this->createMock(ClearSiteData::class);
    $this->sut = new ClearSiteDataSubscriber($this->clearSiteData);
  }

  /**
   * Tests subscribed kernel events and priority.
   */
  public function testGetSubscribedEvents(): void {
    $expected = [KernelEvents::RESPONSE => ['onResponse', -100]];
    $this->assertSame($expected, ClearSiteDataSubscriber::getSubscribedEvents());
  }

  /**
   * Tests that cacheable responses receive config dependency metadata.
   */
  public function testOnResponseAddsCacheableDependencyForCacheableResponse(): void {
    $dependency = $this->createMock(CacheableDependencyInterface::class);
    $this->clearSiteData
      ->expects($this->once())
      ->method('getDependencyMetadata')
      ->willReturn($dependency);
    $this->clearSiteData
      ->method('isEnabled')
      ->willReturn(FALSE);

    $response = new CacheableResponse();
    $event = $this->createResponseEvent($response);

    $this->sut->onResponse($event);

    $this->assertFalse($response->headers->has('Clear-Site-Data'));
  }

  /**
   * Tests that disabled mode skips metadata and does not set the header.
   */
  public function testOnResponseDoesNotSetHeaderWhenDisabled(): void {
    $this->clearSiteData
      ->expects($this->never())
      ->method('getDependencyMetadata');
    $this->clearSiteData
      ->method('isEnabled')
      ->willReturn(FALSE);

    $response = new Response();
    $event = $this->createResponseEvent($response);

    $this->sut->onResponse($event);

    $this->assertFalse($response->headers->has('Clear-Site-Data'));
  }

  /**
   * Tests Clear-Site-Data header values when enabled (quoted directives).
   */
  public function testOnResponseSetsHeaderWhenEnabled(): void {
    $this->clearSiteData
      ->method('getDependencyMetadata')
      ->willReturn($this->createMock(CacheableDependencyInterface::class));
    $this->clearSiteData
      ->method('isEnabled')
      ->willReturn(TRUE);
    $this->clearSiteData
      ->method('getActiveDirectives')
      ->willReturn(['cache', 'cookies']);

    $response = new CacheableResponse();
    $event = $this->createResponseEvent($response);

    $this->sut->onResponse($event);

    $this->assertTrue($response->headers->has('Clear-Site-Data'));
    $this->assertSame(['"cache"', '"cookies"'], $response->headers->all('clear-site-data'));
  }

  /**
   * Tests that plain responses skip cache metadata.
   */
  public function testOnResponsePlainResponseDoesNotCallGetDependencyMetadata(): void {
    $this->clearSiteData
      ->expects($this->never())
      ->method('getDependencyMetadata');
    $this->clearSiteData
      ->method('isEnabled')
      ->willReturn(TRUE);
    $this->clearSiteData
      ->method('getActiveDirectives')
      ->willReturn(['storage']);

    $response = new Response();
    $event = $this->createResponseEvent($response);

    $this->sut->onResponse($event);

    $this->assertSame(['"storage"'], $response->headers->all('clear-site-data'));
  }

  /**
   * Builds a kernel response event wrapping the given response.
   */
  private function createResponseEvent(Response $response): ResponseEvent {
    $kernel = $this->createMock(HttpKernelInterface::class);
    return new ResponseEvent(
      $kernel,
      Request::create('/'),
      HttpKernelInterface::MAIN_REQUEST,
      $response,
    );
  }

}
