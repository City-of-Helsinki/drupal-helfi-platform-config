<?php

declare(strict_types=1);

namespace Drupal\hdbt_admin_tools\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the Image Gallery constraint.
 */
class ImageGalleryConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    assert($constraint instanceof ImageGalleryConstraint);
    $parent = $value->getEntity();

    if ($value->getName() !== 'field_gallery_item') {
      return;
    }

    if (
      $parent &&
      $parent->hasField('field_gallery_item') &&
      $parent->get('field_gallery_item')->count() < 2
    ) {
      $this->context->addViolation($constraint->galleryItemRequired);
    }
  }

}
