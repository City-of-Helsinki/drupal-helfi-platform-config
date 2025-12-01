<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\EventSubscriber;

use Drupal\helfi_platform_config\EventSubscriber\CspCleanSubscriber;
use Drupal\csp\CspEvents;
use Drupal\Core\Config\ImmutableConfig;
use Prophecy\Prophecy\ObjectProphecy;
use Prophecy\Argument;

/**
 * Unit tests for CspCleanSubscriber.
 *
 * @group helfi_platform_config
 * @coversDefaultClass \Drupal\helfi_platform_config\EventSubscriber\CspCleanSubscriber
 */
class CspCleanSubscriberTest extends CspEventSubscriberTestBase {

  /**
   * The Csp config.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected ObjectProphecy $cspConfig;

  /**
   * The event class to test.
   */
  protected ?string $eventClass = CspCleanSubscriber::class;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->cspConfig = $this->prophesize(ImmutableConfig::class);
    $this->cspConfig->get(Argument::any())->willReturn(NULL);
    $this->policy->hasDirective(Argument::any())->willReturn(FALSE);
    $this->configFactory->get('csp.settings')->willReturn($this->cspConfig->reveal());
  }

  /**
   * Tests the getSubscribedEvents method.
   *
   * @covers ::getSubscribedEvents
   */
  public function testGetSubscribedEvents(): void {
    $this->assertEquals([CspEvents::POLICY_ALTER => ['policyAlter', -100]], $this->eventSubscriber->getSubscribedEvents());
  }

  /**
   * Tests cleaning of bad directive values.
   *
   * @covers ::policyAlter
   * @covers ::cleanDirectiveValues
   */
  public function testCleanDirectiveValues(): void {
    foreach ([
      'script-src',
      'style-src',
    ] as $directive) {
      $this->policy->hasDirective($directive)->willReturn(TRUE);
      $this->policy->getDirective($directive)->willReturn(['https://example.com', 'dist']);
      $this->policy->setDirective($directive, ['https://example.com'])->shouldBeCalled();
    }

    $this->eventSubscriber->policyAlter($this->event->reveal());
  }

  /**
   * Tests cleaning of disabled directives.
   *
   * @covers ::policyAlter
   * @covers ::removeDisallowedDirectives
   */
  public function testCleanDisabledDirectives(): void {
    foreach ([
      'script-src',
      'style-src',
    ] as $directive) {
      $this->policy->hasDirective($directive)->willReturn(FALSE);
    }

    $this->policy->hasDirective('script-src-elem')->willReturn(TRUE, FALSE);
    $this->policy->hasDirective('style-src-elem')->willReturn(TRUE, FALSE);
    $this->policy->removeDirective('script-src-elem')->shouldBeCalled();
    $this->policy->removeDirective('style-src-elem')->shouldBeCalled();

    $this->eventSubscriber->policyAlter($this->event->reveal());
  }

}
