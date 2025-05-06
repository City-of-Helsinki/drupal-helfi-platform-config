<?php

declare(strict_types=1);

namespace Drupal\hdbt_admin_tools\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Validates that accordion item content is not empty.
 *
 * @Constraint(
 *   id = "AccordionItemContent",
 *   label = @Translation("Accordion item content is mandatory.", context = "Validation"),
 *   type = "entity:paragraph"
 * )
 */
class AccordionItemContentConstraint extends Constraint {
 /**
   * Message shown for the Hero paragraph.
   *
   * @var string
   */
  public string $accordionItemContentRequired = 'The content contains an accordion item with no content. Add content to the item or remove the accordion entirely.';

}
