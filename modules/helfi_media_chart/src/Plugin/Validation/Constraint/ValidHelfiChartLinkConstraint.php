<?php

declare(strict_types=1);

namespace Drupal\helfi_media_chart\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint as ConstraintAttribute;
use Symfony\Component\Validator\Constraint;

/**
 * Provides a ValidChartLink constraint.
 */
#[ConstraintAttribute(
  id: 'ValidHelfiChartLink',
  label: new TranslatableMarkup('ValidChartLink', options: ['context' => 'Validation']),
)]
final class ValidHelfiChartLinkConstraint extends Constraint {

  /**
   * The error message.
   *
   * @var string
   */
  public string $errorMessage = 'Given host (%value) is not valid, must be one of: %domains';

}
