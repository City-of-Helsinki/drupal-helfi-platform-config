<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\helfi_platform_config\Plugin\Validation\Constraint\MultisiteLinkAccessConstraint;

/**
 * Hook implementations for link access validation.
 */
final class LinkAccessHooks {

  /**
   * Implements hook_validation_constraint_alter().
   *
   * @phpstan-param array<string, array<string, mixed>> $definitions
   */
  #[Hook('validation_constraint_alter')]
  public function validationConstraintAlter(array &$definitions): void {
    if (isset($definitions['LinkAccess'])) {
      $definitions['LinkAccess']['class'] = MultisiteLinkAccessConstraint::class;
    }
  }

}
