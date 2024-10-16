<?php

declare(strict_types = 1);

namespace Drupal\helfi_paragraphs_curated_event_list\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Disallows events that have ended.
 *
 * @Constraint(
 *   id = "CuratedEvent",
 *   label = @Translation("Disallows events that have ended.", context = "Validation"),
 *   type = "entity:paragraph"
 * )
 */
class CuratedEventConstraint extends Constraint
{
  /**
   * Event has ended message.
   */
  public string $containsEndedEvents = 'Event %title has ended. Only upcoming or ongoing events are allowed.';
}
