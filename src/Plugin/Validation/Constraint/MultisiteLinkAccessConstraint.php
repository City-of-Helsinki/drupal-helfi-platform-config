<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Plugin\Validation\Constraint;

use Drupal\link\Plugin\Validation\Constraint\LinkAccessConstraint;

/**
 * Link access constraint that allows multisite content placeholder URIs.
 */
final class MultisiteLinkAccessConstraint extends LinkAccessConstraint {

  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return MultisiteLinkAccessConstraintValidator::class;
  }

}
