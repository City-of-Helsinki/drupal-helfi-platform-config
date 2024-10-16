<?php

declare(strict_types = 1);

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

    // Early return if no events are selected.
    if (empty($events)) {
      return;
    }

    foreach($events as $key => $event) {
      if ($event->end_time->date->getTimeStamp() < time()) {
        $this->context->buildViolation($constraint->containsEndedEvents, ['%title' => $event->title->value])
          ->atPath($key)
          ->addViolation();
      }
    }
  }
}
