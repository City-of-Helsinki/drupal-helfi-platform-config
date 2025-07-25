<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_recommendations\Unit;

use Drupal\csp\Csp;
use Drupal\csp\CspEvents;
use Drupal\csp\Event\PolicyAlterEvent;
use Drupal\helfi_csp\EventSubscriber\CspEventSubscriber;
use Drupal\Tests\UnitTestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Unit tests for CspEventSubscriber.
 *
 * @group helfi_csp
 * @coversDefaultClass \Drupal\helfi_csp\EventSubscriber\CspEventSubscriber
 */
class CspEventSubscriberTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * The CspEventSubscriber.
   *
   * @var \Drupal\helfi_csp\EventSubscriber\CspEventSubscriber
   */
  protected CspEventSubscriber $cspEventSubscriber;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

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
   * Tests cleaning of bad directive values.
   *
   * @covers ::policyAlter
   * @covers ::cleanDirectiveValues
   */
  public function testShowRecommendationsNoFields(): void {
    $policy = $this->prophesize(Csp::class);
    $policy->hasDirective('script-src')->willReturn(TRUE);
    $policy->getDirective('script-src')->willReturn(['https://example.com', 'dist']);
    $policy->hasDirective('style-src')->willReturn(TRUE);
    $policy->getDirective('style-src')->willReturn(['https://example.com', 'dist']);

    $event = $this->prophesize(PolicyAlterEvent::class);
    $event->getPolicy()->willReturn($policy->reveal());

    $policy->setDirective('script-src', ['https://example.com'])->shouldBeCalled();
    $policy->setDirective('style-src', ['https://example.com'])->shouldBeCalled();

    $this->cspEventSubscriber->policyAlter($event->reveal());
  }

}
