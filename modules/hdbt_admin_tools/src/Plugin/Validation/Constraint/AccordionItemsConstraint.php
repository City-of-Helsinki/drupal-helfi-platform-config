<?php

declare(strict_types=1);

namespace Drupal\hdbt_admin_tools\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Validation constraint for accordions that must have at least one item.
 */
#[Constraint(
  id: 'AccordionItems',
  label: new TranslatableMarkup('Accordion must contain at least one item.', [], ['context' => 'Validation'])
)]
class AccordionItemsConstraint extends SymfonyConstraint {

  /**
   * Message shown for the Accordion paragraph.
   *
   * @var string
   */
  public string $accordionItemsRequired = 'The content contains an accordion with no items. Either remove the accordion entirely or add a new item to it.';

}
