<?php

declare(strict_types=1);

namespace Drupal\hdbt_admin_tools\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the Hero constraint.
 */
class HeroConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint) {
    assert($constraint instanceof HeroConstraint);
    $parent = $value->getEntity();

    if ($value->getName() !== 'field_has_hero') {
      return;
    }

    if (
      $parent &&
      $parent->hasField('field_hero') &&
      !$parent->get('field_hero')->entity &&
      $value->value
    ) {
      $this->context->addViolation($constraint->heroRequired);
    }
  }

}
