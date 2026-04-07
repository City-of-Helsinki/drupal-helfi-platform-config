<?php

declare(strict_types=1);

namespace Drupal\helfi_react_search\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Checks that the submitted value is a valid LinkedEvents API query string.
 */
#[Constraint(
  id: 'LinkedEventsQueryString',
  label: new TranslatableMarkup('LinkedEvents API query string', options: ['context' => 'Validation']),
  type: ['string']
)]
class LinkedEventsQueryStringConstraint extends SymfonyConstraint {

  #[HasNamedArguments]
  public function __construct(
    public readonly array $disallowedQueryParameters = [
      'all_ongoing',
      'all_ongoing_AND',
      'all_ongoing_OR',
    ],
    public readonly string $notValid = '%value is not a valid LinkedEvents API query string. Please remove the disallowed query parameters: %disallowedQueryParameters. Parameter <code>all_ongoing</code> is no longer needed and parameters <code>all_ongoing_AND</code> and <code>all_ongoing_OR</code> should be replaced with <code>full_text</code>.',
    mixed ...$args,
  ) {
    parent::__construct(...$args);
  }

}
