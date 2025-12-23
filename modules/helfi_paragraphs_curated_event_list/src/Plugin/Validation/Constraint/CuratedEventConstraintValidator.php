<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_curated_event_list\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the CuratedEventConstraint constraint.
 */
class CuratedEventConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint) {
    assert($constraint instanceof CuratedEventConstraint);

    $events = $value->referencedEntities();

    if (empty($events)) {
      return;
    }

    foreach ($events as $key => $event) {
      if ($event->hasEnded()) {
        $this->context->buildViolation($constraint->containsEndedEvents, ['%title' => $event->label()])
          ->atPath((string) $key)
          ->addViolation();
      }
    }
  }

}
