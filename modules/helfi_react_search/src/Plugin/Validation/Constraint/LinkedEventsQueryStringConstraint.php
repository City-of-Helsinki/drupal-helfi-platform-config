<?php

declare(strict_types=1);

namespace Drupal\helfi_react_search\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that the submitted value is a valid LinkedEvents API query string.
 *
 * @Constraint(
 *   id = "LinkedEventsQueryString",
 *   label = @Translation("LinkedEvents API query string", context = "Validation"),
 *   type = "string"
 * )
 */
class LinkedEventsQueryStringConstraint extends Constraint {

  /**
   * The disallowed query parameters.
   */
  public array $disallowedQueryParameters = [
    'all_ongoing',
    'all_ongoing_AND',
    'all_ongoing_OR',
  ];

  /**
   * The error message.
   *
   * Will be shown if the value is not a valid LinkedEvents API query string.
   */
  public string $notValid = '%value is not a valid LinkedEvents API query string. Please remove the disallowed query parameters: %disallowedQueryParameters. Parameter <code>all_ongoing</code> is no longer needed and parameters <code>all_ongoing_AND</code> and <code>all_ongoing_OR</code> should be replaced with <code>full_text</code>.';

}
