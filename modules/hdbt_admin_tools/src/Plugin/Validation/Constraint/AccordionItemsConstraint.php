<?php

declare(strict_types=1);

namespace Drupal\hdbt_admin_tools\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Validates that accordion has at least one item.
 *
 * @Constraint(
 *   id = "AccordionItems",
 *   label = @Translation("Accordion must contain at least one item", context = "Validation"),
 * )
 */
class AccordionItemsConstraint extends Constraint {

  public string $accordionItemsRequired = 'The content contains an accordion with no items. Either remove the accordion entirely or add a new item to it.';

}
