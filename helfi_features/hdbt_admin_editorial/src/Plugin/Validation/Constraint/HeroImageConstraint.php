<?php

namespace Drupal\hdbt_admin_editorial\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that the image is present if design needs the image.
 *
 * @Constraint(
 *   id = "HeroImage",
 *   label = @Translation("Hero image is missing, but it is mandatory with selected design.", context = "Validation"),
 *   type = "entity:paragraph"
 * )
 */
class HeroImageConstraint extends Constraint {
  /**
   * Message shown for the Hero paragraph.
   *
   * @var string
   */
  public string $heroImageRequired = 'Image is mandatory with the selected Hero design.';

  /**
   * Designs which need an image.
   *
   * @var array
   */
  public array $heroImageMandatoryDesigns = [
    'background-image',
    'with-image-bottom',
    'with-image-left',
    'with-image-right',
    'diagonal',
  ];

}
