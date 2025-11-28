<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\EventSubscriber;

use Drupal\helfi_platform_config\EventSubscriber\CspCommonSubscriber;
use Prophecy\Argument;

/**
 * Unit tests for CspCommonSubscriber.
 *
 * @group helfi_platform_config
 * @coversDefaultClass \Drupal\helfi_platform_config\EventSubscriber\CspCommonSubscriber
 */
class CspCommonSubscriberTest extends CspEventSubscriberTestBase {

  /**
   * The event class to test.
   */
  protected ?string $eventClass = CspCommonSubscriber::class;

  /**
   * Test hash appending.
   */
  public function testHashAppending(): void {
    $this->moduleHandler->moduleExists('big_pipe')->willReturn(TRUE);
    $this->policyHelper->appendHash(Argument::any(), 'script', 'elem', ['unsafe-inline'], Argument::that(fn ($argument) => is_string($argument) && str_starts_with($argument, 'sha256-')))->shouldBeCalled();

    $this->eventSubscriber->policyAlter($this->event->reveal());
  }

}
