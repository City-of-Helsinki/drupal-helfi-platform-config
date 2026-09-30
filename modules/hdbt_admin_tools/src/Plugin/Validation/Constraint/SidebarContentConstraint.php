<?php

declare(strict_types=1);

namespace Drupal\hdbt_admin_tools\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint as ConstraintAttribute;
use Symfony\Component\Validator\Constraint;

/**
 * Checks if sidebar has content.
 *
 * @todo UHF-13030 Remove this when the field is removed.
 */
#[ConstraintAttribute(
  id: 'SidebarContent',
  label: new TranslatableMarkup("There shouldn't be any content in the sidebar", options: ['context' => 'Validation']),
  type: 'entity:paragraph',
)]
class SidebarContentConstraint extends Constraint {

  /**
   * Message shown for the sidebar content paragraph.
   *
   * @var string
   */
  public string $sidebarContentExists = 'The sidebar content area will be removed. Please move the content to the upper or lower content area or remove it.';

}
