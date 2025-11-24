<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\EventSubscriber;

use Drupal\helfi_platform_config\EventSubscriber\CspCleanSubscriber;
use Drupal\Core\Config\ImmutableConfig;
use Prophecy\Prophecy\ObjectProphecy;

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
  protected string $eventClass = CspCleanSubscriber::class;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->cspConfig = $this->prophesize(ImmutableConfig::class);
    $this->cspConfig->get('script-src-elem')->willReturn(NULL);
    $this->cspConfig->get('style-src-elem')->willReturn(NULL);
    $this->policy->hasDirective('script-src-elem')->willReturn(FALSE);
    $this->policy->hasDirective('style-src-elem')->willReturn(FALSE);
    $this->configFactory->get('csp.settings')->willReturn($this->cspConfig->reveal());
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
