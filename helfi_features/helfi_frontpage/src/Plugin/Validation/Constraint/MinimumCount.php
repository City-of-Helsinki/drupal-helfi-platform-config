<?php

declare(strict_types = 1);

namespace Drupal\helfi_frontpage\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that the field has a minimum amount of values.
 *
 * @Constraint(
 *   id = "MinimumCount",
 *   label = @Translation("Minimum count", context = "Validation"),
 *   type = "string"
 * )
 */
class MinimumCount extends Constraint {
  /**
   * The minimum count of values for the field.
   *
   * @var int
   */
  public $count;
  /**
   * Validation message when validation fails.
   *
   * @var int
   */
  public $tooFewValues = 'Field %field expects at least %count values';

}
