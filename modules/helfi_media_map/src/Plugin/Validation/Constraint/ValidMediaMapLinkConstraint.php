<?php

declare(strict_types = 1);

namespace Drupal\helfi_media_map\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Provides a ValidMapLink constraint.
 *
 * @Constraint(
 *   id = "ValidMediaMapLink",
 *   label = @Translation("ValidMapLink", context = "Validation"),
 * )
 */
final class ValidMediaMapLinkConstraint extends Constraint {

  /**
   * The error message.
   *
   * @var string
   */
  public string $errorMessage = 'Given host (%value) is not valid, must be one of: %domains';

}
