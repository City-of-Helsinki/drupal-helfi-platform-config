<?php

namespace Drupal\hdbt_admin_tools\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that the image gallery has at least two items.
 *
 * @Constraint(
 *   id = "ImageGallery",
 *   label = @Translation("There should be at least two gallery items.", context = "Validation"),
 * )
 */
class ImageGalleryConstraint extends Constraint {

  /**
   * Message shown for the Image gallery paragraph.
   *
   * @var string
   */
  public string $galleryItemRequired = 'Image gallery paragraph should have at least two items.';

}
