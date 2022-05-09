<?php

declare(strict_types = 1);

namespace Drupal\helfi_charts\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Provides a ValidChartLink constraint.
 *
 * @Constraint(
 *   id = "ValidHelfiChartLink",
 *   label = @Translation("ValidChartLink", context = "Validation"),
 * )
 */
final class ValidHelfiChartLinkConstraint extends Constraint {

  /**
   * The error message.
   *
   * @var string
   */
  public string $errorMessage = 'Given host (%value) is not valid, must be one of: %domains';

}
