<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_search\Traits;

use Drupal\Component\Datetime\TimeInterface;

/**
 * Provides a clock the test can move forward.
 *
 * @see \Drupal\helfi_search\Queue\QueueManager
 */
trait TestClockTrait {

  /**
   * The time the system under test believes it is.
   */
  private int $now;

  /**
   * Starts the clock at the container's request time.
   */
  private function startClock(): void {
    $this->now = $this->container->get(TimeInterface::class)->getRequestTime();
  }

  /**
   * A clock reading the time the test has moved to.
   */
  private function time(): TimeInterface {
    return new class(fn (): int => $this->now) implements TimeInterface {

      public function __construct(private readonly \Closure $now) {
      }

      /**
       * {@inheritdoc}
       */
      public function getRequestTime(): int {
        return ($this->now)();
      }

      /**
       * {@inheritdoc}
       */
      public function getRequestMicroTime(): float {
        return (float) ($this->now)();
      }

      /**
       * {@inheritdoc}
       */
      public function getCurrentTime(): int {
        return ($this->now)();
      }

      /**
       * {@inheritdoc}
       */
      public function getCurrentMicroTime(): float {
        return (float) ($this->now)();
      }

    };
  }

  /**
   * Moves the clock forward.
   */
  private function advanceTime(int $seconds): void {
    $this->now += $seconds;
  }

}
