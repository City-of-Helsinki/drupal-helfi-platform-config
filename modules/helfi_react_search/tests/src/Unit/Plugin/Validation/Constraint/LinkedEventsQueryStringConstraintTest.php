<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_react_search\Unit\Plugin\Validation\Constraint;

use Drupal\helfi_react_search\Plugin\Validation\Constraint\LinkedEventsQueryStringConstraint;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the LinkedEventsQueryStringConstraint.
 */
#[Group('helfi_react_search')]
final class LinkedEventsQueryStringConstraintTest extends UnitTestCase {

  /**
   * Tests default constraint options.
   */
  public function testDefaultOptions(): void {
    $constraint = new LinkedEventsQueryStringConstraint();

    $this->assertSame(
      [
        'all_ongoing',
        'all_ongoing_AND',
        'all_ongoing_OR',
      ],
      $constraint->disallowedQueryParameters,
    );
    $this->assertNotSame('', $constraint->notValid);
    $this->assertStringContainsString('%value', $constraint->notValid);
    $this->assertStringContainsString('%disallowedQueryParameters', $constraint->notValid);
  }

}
