<?php

declare(strict_types=1);

namespace Drupal\hdbt_admin_tools\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks if sidebar has content.
 *
 * @Constraint(
 *   id = "SidebarContent",
 *   label = @Translation("There shouldn't be any content in the sidebar", context = "Validation"),
 *   type = "entity:paragraph"
 * )
 */
class SidebarContentConstraint extends Constraint {

  /**
   * Message shown for the sidebar content paragraph.
   *
   * @var string
   */
  public string $sidebarContentExists = 'The sidebar content area will be removed. Please move the content to the upper or lower content area or remove it.';

}
