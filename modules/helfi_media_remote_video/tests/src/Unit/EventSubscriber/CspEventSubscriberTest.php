<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_media_remote_video\Unit;

use Drupal\helfi_media_remote_video\EventSubscriber\CspEventSubscriber;
use Drupal\Tests\helfi_platform_config\Unit\EventSubscriber\CspEventSubscriberTestBase;
use Prophecy\Argument;

/**
 * Unit tests for CspEventSubscriber.
 *
 * @group helfi_media_remote_video
 * @coversDefaultClass \Drupal\helfi_media_remote_video\EventSubscriber\CspEventSubscriber
 */
class CspEventSubscriberTest extends CspEventSubscriberTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->eventSubscriber = new CspEventSubscriber(
      $this->environmentResolver->reveal(),
      $this->configFactory->reveal(),
      $this->moduleHandler->reveal(),
    );
  }

  /**
   * Tests policy alteration.
   *
   * @covers ::policyAlter
   */
  public function testPolicyAlterWithLocalEnvironment(): void {
    $this->policy->fallbackAwareAppendIfEnabled('connect-src', Argument::Any())->shouldBeCalled();
    $this->policy->fallbackAwareAppendIfEnabled('frame-src', Argument::Any())->shouldBeCalled();
    $this->policy->fallbackAwareAppendIfEnabled('object-src', Argument::Any())->shouldBeCalled();

    $this->eventSubscriber->policyAlter($this->event->reveal());
  }

}
