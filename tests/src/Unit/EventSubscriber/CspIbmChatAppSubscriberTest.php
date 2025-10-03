<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\EventSubscriber;

use Drupal\helfi_platform_config\EventSubscriber\CspIbmChatAppSubscriber;
use Prophecy\Argument;

/**
 * Unit tests for CspIbmChatAppSubscriber.
 *
 * @group helfi_platform_config
 * @coversDefaultClass \Drupal\helfi_platform_config\EventSubscriber\CspIbmChatAppSubscriber
 */
class CspIbmChatAppSubscriberTest extends CspEventSubscriberTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->eventSubscriber = new CspIbmChatAppSubscriber(
      $this->environmentResolver->reveal(),
      $this->configFactory->reveal(),
      $this->moduleHandler->reveal(),
    );
  }

  /**
   * Tests appending of directive values.
   *
   * @covers ::policyAlter
   */
  public function testAppendDirectiveValues(): void {
    $this->policy->fallbackAwareAppendIfEnabled('connect-src', Argument::any())->shouldBeCalled();
    $this->policy->fallbackAwareAppendIfEnabled('font-src', Argument::any())->shouldBeCalled();
    $this->policy->fallbackAwareAppendIfEnabled('frame-src', Argument::any())->shouldBeCalled();
    $this->policy->fallbackAwareAppendIfEnabled('img-src', Argument::any())->shouldBeCalled();
    $this->policy->fallbackAwareAppendIfEnabled('script-src', Argument::any())->shouldBeCalled();
    $this->policy->fallbackAwareAppendIfEnabled('style-src', Argument::any())->shouldBeCalled();

    $this->eventSubscriber->policyAlter($this->event->reveal());
  }

}
