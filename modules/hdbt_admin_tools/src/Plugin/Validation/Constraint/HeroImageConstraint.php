<?php

declare(strict_types=1);

namespace Drupal\hdbt_admin_tools\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint as ConstraintAttribute;
use Symfony\Component\Validator\Constraint;

/**
 * Checks that the image is present if design needs the image.
 */
#[ConstraintAttribute(
  id: 'HeroImage',
  label: new TranslatableMarkup('Hero image is missing, but it is mandatory with selected design.', options: ['context' => 'Validation']),
  type: 'entity:paragraph',
)]
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
