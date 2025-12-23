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

    foreach ($events as $delta => $event) {
      try {
        if ($event->hasEnded()) {
          $this->context->buildViolation($constraint->containsEndedEvents, ['%title' => $event->label()])
            ->atPath((string) $delta . '.target_id')
            ->addViolation();
        }
      } catch (\Exception $e) {
        // Log the error but don't block the form submission.
        \Drupal::logger('helfi_paragraphs_curated_event_list')->error(
          'Error validating event @id: @message',
          ['@id' => $event->id(), '@message' => $e->getMessage()]
        );
      }
    }
  }

}
