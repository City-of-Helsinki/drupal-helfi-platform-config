<?php

declare(strict_types=1);

namespace Drupal\hdbt_admin_tools\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the MandatoryIntroImageConstraint constraint.
 */
class HeroImageConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint) {
    assert($constraint instanceof HeroImageConstraint);
    $parent = $value->getEntity();

    if ($parent && $parent->bundle() == 'hero') {
      $hero_design = $parent->field_hero_design->value;

      if (
        in_array($hero_design, $constraint->heroImageMandatoryDesigns) &&
        !$value->getValue()
      ) {
        $this->context->addViolation($constraint->heroImageRequired);
      }
    }
  }

}
