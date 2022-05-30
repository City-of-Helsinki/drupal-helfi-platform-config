<?php

declare(strict_types = 1);

namespace Drupal\helfi_frontpage\Plugin\Validation\Constraint;

use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates that field has a minimum count of values.
 */
class MinimumCountValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($items, $constraint) {
    if ($items->count() < $constraint->count) {
      $this->context->addViolation($constraint->tooFewValues, [
        '%field' => $items->getFieldDefinition()->getLabel(),
        '%count' => $constraint->count,
      ]);
    }
  }

}
