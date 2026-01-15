<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\EventSubscriber;

use Drupal\helfi_platform_config\EventSubscriber\CspSubscriberBase;
use Drupal\csp\CspEvents;

/**
 * Unit tests for CspSubscriberBase.
 *
 * @group helfi_platform_config
 * @coversDefaultClass \Drupal\helfi_platform_config\EventSubscriber\CspSubscriberBase
 */
class CspSubscriberBaseTest extends CspEventSubscriberTestBase {

  /**
   * Tests getSubscribedEvents method.
   *
   * @covers ::getSubscribedEvents
   */
  public function testGetSubscribedEvents(): void {
    $this->eventSubscriber = new class (
      $this->configFactory->reveal(),
      $this->moduleHandler->reveal(),
      $this->environmentResolver,
      $this->policyHelper->reveal(),
    ) extends CspSubscriberBase {};

    $this->assertEquals([CspEvents::POLICY_ALTER => 'policyAlter'], $this->eventSubscriber->getSubscribedEvents());
  }

  /**
   * Tests policyAlter method with all directives.
   *
   * @covers ::policyAlter
   */
  public function testPolicyAlterWithAllDirectives(): void {
    $this->eventSubscriber = new class (
      $this->configFactory->reveal(),
      $this->moduleHandler->reveal(),
      $this->environmentResolver,
      $this->policyHelper->reveal(),
    ) extends CspSubscriberBase {
      const CONNECT_SRC = ['https://example.com'];
      const FONT_SRC = ['https://example.com'];
      const FRAME_SRC = ['https://example.com'];
      const IMG_SRC = ['https://example.com'];
      const MEDIA_SRC = ['https://example.com'];
      const OBJECT_SRC = ['https://example.com'];
      const SCRIPT_SRC = ['https://example.com'];
      const STYLE_SRC = ['https://example.com'];
    };

    $this->policy->fallbackAwareAppendIfEnabled('connect-src', ['https://example.com'])->shouldBeCalled();
    $this->policy->fallbackAwareAppendIfEnabled('font-src', ['https://example.com'])->shouldBeCalled();
    $this->policy->fallbackAwareAppendIfEnabled('frame-src', ['https://example.com'])->shouldBeCalled();
    $this->policy->fallbackAwareAppendIfEnabled('img-src', ['https://example.com'])->shouldBeCalled();
    $this->policy->fallbackAwareAppendIfEnabled('media-src', ['https://example.com'])->shouldBeCalled();
    $this->policy->fallbackAwareAppendIfEnabled('object-src', ['https://example.com'])->shouldBeCalled();
    $this->policy->fallbackAwareAppendIfEnabled('script-src', ['https://example.com'])->shouldBeCalled();
    $this->policy->fallbackAwareAppendIfEnabled('style-src', ['https://example.com'])->shouldBeCalled();

    $this->eventSubscriber->policyAlter($this->event->reveal());
  }

  /**
   * Tests policyAlter with some directives.
   *
   * @covers ::policyAlter
   */
  public function testPolicyAlterWithSomeDirectives(): void {
    $this->eventSubscriber = new class (
      $this->configFactory->reveal(),
      $this->moduleHandler->reveal(),
      $this->environmentResolver,
      $this->policyHelper->reveal(),
    ) extends CspSubscriberBase {
      const CONNECT_SRC = ['https://example.com'];
      const SCRIPT_SRC = ['https://example.com'];
    };

    $this->policy->fallbackAwareAppendIfEnabled('connect-src', ['https://example.com'])->shouldBeCalled();
    $this->policy->fallbackAwareAppendIfEnabled('font-src', ['https://example.com'])->shouldNotBeCalled();
    $this->policy->fallbackAwareAppendIfEnabled('frame-src', ['https://example.com'])->shouldNotBeCalled();
    $this->policy->fallbackAwareAppendIfEnabled('img-src', ['https://example.com'])->shouldNotBeCalled();
    $this->policy->fallbackAwareAppendIfEnabled('media-src', ['https://example.com'])->shouldNotBeCalled();
    $this->policy->fallbackAwareAppendIfEnabled('object-src', ['https://example.com'])->shouldNotBeCalled();
    $this->policy->fallbackAwareAppendIfEnabled('script-src', ['https://example.com'])->shouldBeCalled();
    $this->policy->fallbackAwareAppendIfEnabled('style-src', ['https://example.com'])->shouldNotBeCalled();

    $this->eventSubscriber->policyAlter($this->event->reveal());
  }

  /**
   * Tests policyAlter with a met module dependency.
   *
   * @covers ::policyAlter
   */
  public function testPolicyAlterWithMetModuleDependency(): void {
    $this->moduleHandler->moduleExists('test_module')->willReturn(TRUE);

    $this->eventSubscriber = new class (
      $this->configFactory->reveal(),
      $this->moduleHandler->reveal(),
      $this->environmentResolver,
      $this->policyHelper->reveal(),
    ) extends CspSubscriberBase {
      const MODULE_DEPENDENCY = 'test_module';
      const CONNECT_SRC = ['https://example.com'];
      const SCRIPT_SRC = ['https://example.com'];
    };

    $this->policy->fallbackAwareAppendIfEnabled('connect-src', ['https://example.com'])->shouldBeCalled();
    $this->policy->fallbackAwareAppendIfEnabled('script-src', ['https://example.com'])->shouldBeCalled();

    $this->eventSubscriber->policyAlter($this->event->reveal());
  }

  /**
   * Tests policyAlter with an unmet module dependency.
   *
   * @covers ::policyAlter
   */
  public function testPolicyAlterWithUnmetModuleDependency(): void {
    $this->moduleHandler->moduleExists('test_module')->willReturn(FALSE);

    $this->eventSubscriber = new class (
      $this->configFactory->reveal(),
      $this->moduleHandler->reveal(),
      $this->environmentResolver,
      $this->policyHelper->reveal(),
    ) extends CspSubscriberBase {
      const MODULE_DEPENDENCY = 'test_module';
      const CONNECT_SRC = ['https://example.com'];
      const SCRIPT_SRC = ['https://example.com'];
    };

    $this->policy->fallbackAwareAppendIfEnabled('connect-src', ['https://example.com'])->shouldNotBeCalled();
    $this->policy->fallbackAwareAppendIfEnabled('script-src', ['https://example.com'])->shouldNotBeCalled();

    $this->eventSubscriber->policyAlter($this->event->reveal());
  }

}
