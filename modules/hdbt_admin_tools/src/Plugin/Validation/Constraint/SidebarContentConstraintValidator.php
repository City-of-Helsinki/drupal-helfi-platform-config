<?php

declare(strict_types=1);

namespace Drupal\hdbt_admin_tools\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Validates the Sidebar Content constraint.
 *
 * @todo UHF-13030 Remove this when the field is removed.
 */
class SidebarContentConstraintValidator extends ConstraintValidator {
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    assert($constraint instanceof SidebarContentConstraint);

    $messenger = \Drupal::service(MessengerInterface::class);

    if ($value->count() > 0) {
      $this->context->addViolation($constraint->sidebarContentExists);
      $messenger->addError($this->t('The sidebar content area will be removed. Please move the content to the upper or lower content area or remove it.'));
    }
  }

}
