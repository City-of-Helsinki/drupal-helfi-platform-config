<?php

declare(strict_types=1);

namespace Drupal\hdbt_admin_tools\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the AccordionItemContent constraint.
 */
class AccordionItemContentConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    assert($constraint instanceof AccordionItemContentConstraint);

    // This constraint should apply to a field item.
    $parent = $value->getEntity();

    // Make sure we're validating the correct field on the right paragraph type.
    if ($value->getName() !== 'field_accordion_item_content') {
      return;
    }

    // Check if the field is empty.
    if (
      $parent &&
      $parent->hasField('field_accordion_item_content') &&
      $parent->get('field_accordion_item_content')->isEmpty()
    ) {
      $this->context->addViolation($constraint->accordionItemContentRequired);
    }
  }

}
