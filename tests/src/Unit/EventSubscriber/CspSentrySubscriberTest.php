<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\EventSubscriber;

use Drupal\helfi_platform_config\EventSubscriber\CspSentrySubscriber;
use Prophecy\Argument;

/**
 * Unit tests for CspSentrySubscriber.
 *
 * @group helfi_platform_config
 * @coversDefaultClass \Drupal\helfi_platform_config\EventSubscriber\CspSentrySubscriber
 */
class CspSentrySubscriberTest extends CspEventSubscriberTestBase {

  /**
   * The event class to test.
   */
  protected string $eventClass = CspSentrySubscriber::class;

  /**
   * Tests policy alteration with Sentry DSN.
   *
   * @covers ::policyAlter
   */
  public function testPolicyAlterWithSentryDsn(): void {
    putenv('SENTRY_DSN_PUBLIC=https://randomhash@sentry.test.example.com/123');
    $this->policy->fallbackAwareAppendIfEnabled('connect-src', ['https://sentry.test.example.com'])->shouldBeCalled();
    $this->eventSubscriber->policyAlter($this->event->reveal());
  }

  /**
   * Tests policy alteration with a different Sentry DSN.
   *
   * @covers ::policyAlter
   */
  public function testPolicyAlterWithDifferentSentryDsn(): void {
    // Set a different SENTRY_DSN_PUBLIC environment variable.
    putenv('SENTRY_DSN_PUBLIC=http://anotherrandomhash@sentry.example.com/321');
    $this->policy->fallbackAwareAppendIfEnabled('connect-src', ['http://sentry.example.com'])->shouldBeCalled();
    $this->eventSubscriber->policyAlter($this->event->reveal());
  }

  /**
   * Tests policy alteration with no Sentry DSN.
   *
   * @covers ::policyAlter
   */
  public function testPolicyAlterWithNoSentryDsn(): void {
    // Unset the SENTRY_DSN_PUBLIC environment variable.
    putenv('SENTRY_DSN_PUBLIC');
    $this->policy->fallbackAwareAppendIfEnabled('connect-src', Argument::any())->shouldNotBeCalled();
    $this->eventSubscriber->policyAlter($this->event->reveal());
  }

}
