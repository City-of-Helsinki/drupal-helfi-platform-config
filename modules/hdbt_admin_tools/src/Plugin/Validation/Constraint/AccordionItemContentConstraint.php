<?php

declare(strict_types=1);

namespace Drupal\hdbt_admin_tools\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Validation constraint for accordion item content.
 */
#[Constraint(
  id: 'AccordionItemContent',
  label: new TranslatableMarkup('Accordion item content is mandatory.', [], ['context' => 'Validation']),
  type: 'entity:paragraph'
)]
class AccordionItemContentConstraint extends SymfonyConstraint {

  /**
   * Message shown for the Accordion Item paragraph.
   *
   * @var string
   */
  public string $accordionItemContentRequired = 'The content contains an accordion item with no content. Add content to the item or remove the accordion entirely.';

}
