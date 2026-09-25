<?php

declare(strict_types=1);

namespace Drupal\hdbt_admin_tools\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint as ConstraintAttribute;
use Symfony\Component\Validator\Constraint;

/**
 * Checks that the hero is present if the boolean value has been enabled.
 */
#[ConstraintAttribute(
  id: 'Hero',
  label: new TranslatableMarkup('Hero is missing.', options: ['context' => 'Validation']),
)]
class HeroConstraint extends Constraint {

  /**
   * Message shown for the Hero paragraph.
   *
   * @var string
   */
  public string $heroRequired = 'Hero paragraph is mandatory if the Hero checkbox has been selected. Either unselect the checkbox or create the hero paragraph by clicking the Add Hero button.';

}
