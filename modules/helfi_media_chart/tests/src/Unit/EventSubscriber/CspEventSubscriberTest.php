<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_media_chart\Unit;

use DG\BypassFinals;
use Drupal\csp\Csp;
use Drupal\csp\CspEvents;
use Drupal\csp\Event\PolicyAlterEvent;
use Drupal\helfi_media_chart\EventSubscriber\CspEventSubscriber;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;

/**
 * Unit tests for CspEventSubscriber.
 *
 * @group helfi_media_chart
 * @coversDefaultClass \Drupal\helfi_media_chart\EventSubscriber\CspEventSubscriber
 */
class CspEventSubscriberTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * The CspEventSubscriber.
   *
   * @var \Drupal\helfi_media_chart\EventSubscriber\CspEventSubscriber
   */
  protected CspEventSubscriber $cspEventSubscriber;

  /**
   * The Event.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected ObjectProphecy $event;

  /**
   * The Csp policy.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected ObjectProphecy $policy;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    BypassFinals::enable();

    $this->event = $this->prophesize(PolicyAlterEvent::class);
    $this->policy = $this->prophesize(Csp::class);
    $this->event->getPolicy()->willReturn($this->policy->reveal());

    $this->cspEventSubscriber = new CspEventSubscriber();
  }

  /**
   * Tests the getSubscribedEvents method.
   *
   * @covers ::getSubscribedEvents
   */
  public function testGetSubscribedEvents(): void {
    $this->assertEquals([CspEvents::POLICY_ALTER => 'policyAlter'], CspEventSubscriber::getSubscribedEvents());
  }

  /**
   * Tests policy alteration.
   *
   * @covers ::policyAlter
   */
  public function testPolicyAlterWithLocalEnvironment(): void {
    $this->policy->fallbackAwareAppendIfEnabled('frame-src', Argument::Any())->shouldBeCalled();
    $this->policy->fallbackAwareAppendIfEnabled('object-src', Argument::Any())->shouldBeCalled();

    $this->cspEventSubscriber->policyAlter($this->event->reveal());
  }

}
