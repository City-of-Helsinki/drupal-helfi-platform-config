<?php

declare(strict_types=1);

namespace Drupal\hdbt_admin_tools\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the AccordionItems constraint.
 */
class AccordionItemsConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    assert($constraint instanceof AccordionItemsConstraint);
    $parent = $value->getEntity();

    if ($value->getName() !== 'field_accordion_items') {
      return;
    }

    if (
      $parent &&
      $parent->hasField('field_accordion_items') &&
      $parent->get('field_accordion_items')->count() === 0
    ) {
      $this->context->addViolation($constraint->accordionItemsRequired);
    }
  }

}
