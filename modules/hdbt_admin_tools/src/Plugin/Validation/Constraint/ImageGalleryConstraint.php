<?php

declare(strict_types=1);

namespace Drupal\hdbt_admin_tools\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint as ConstraintAttribute;
use Symfony\Component\Validator\Constraint;

/**
 * Checks that the image gallery has at least two items.
 */
#[ConstraintAttribute(
  id: 'ImageGallery',
  label: new TranslatableMarkup('There should be at least two gallery items.', options: ['context' => 'Validation']),
  type: 'entity:paragraph',
)]
class ImageGalleryConstraint extends Constraint {

  /**
   * Message shown for the Image gallery paragraph.
   *
   * @var string
   */
  public string $galleryItemRequired = 'Image gallery paragraph should have at least two items.';

}
