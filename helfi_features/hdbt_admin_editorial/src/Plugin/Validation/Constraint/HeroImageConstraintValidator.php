<?php

namespace Drupal\hdbt_admin_editorial\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the MandatoryIntroImageConstraint constraint.
 */
class HeroImageConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($items, Constraint $constraint) {
    $parent = $items->getEntity();

    if ($parent && $parent->bundle() == 'hero') {
      $hero_design = $parent->field_hero_design->value;

      if (
        in_array($hero_design, $constraint->heroImageMandatoryDesigns) &&
        !$items->getValue()
      ) {
        $this->context->addViolation($constraint->heroImageRequired);
      }
    }
  }

}
