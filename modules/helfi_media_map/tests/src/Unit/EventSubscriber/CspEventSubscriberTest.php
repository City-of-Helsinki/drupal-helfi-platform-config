<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_media_map\Unit;

use Drupal\helfi_media_map\EventSubscriber\CspEventSubscriber;
use Prophecy\Argument;
use Drupal\Tests\helfi_platform_config\Unit\EventSubscriber\CspEventSubscriberTestBase;

/**
 * Unit tests for CspEventSubscriber.
 *
 * @group helfi_media_map
 * @coversDefaultClass \Drupal\helfi_media_map\EventSubscriber\CspEventSubscriber
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
  public function testPolicyAlter(): void {
    $this->policy->fallbackAwareAppendIfEnabled('frame-src', Argument::Any())->shouldBeCalled();
    $this->policy->fallbackAwareAppendIfEnabled('object-src', Argument::Any())->shouldBeCalled();

    $this->eventSubscriber->policyAlter($this->event->reveal());
  }

}
