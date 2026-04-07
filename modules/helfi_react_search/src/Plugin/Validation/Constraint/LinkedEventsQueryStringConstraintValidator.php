<?php

declare(strict_types=1);

namespace Drupal\helfi_react_search\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the LinkedEvents API query string.
 */
class LinkedEventsQueryStringConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint): void {
    assert($constraint instanceof LinkedEventsQueryStringConstraint);

    foreach ($value as $item) {
      if (!str_starts_with($item->value, '?')) {
        // Not a query string.
        continue;
      }

      parse_str(substr($item->value, 1), $parsed);
      $disallowedQueryParameters = array_filter(array_keys($parsed), fn($queryParameter) => $this->isDisallowedQueryParameter($constraint, $queryParameter));

      if ($disallowedQueryParameters) {
        $this->context->buildViolation($constraint->notValid)
          ->setParameter('%value', $item->value)
          ->setParameter('%disallowedQueryParameters', implode(', ', $disallowedQueryParameters))
          ->addViolation();
      }
    }
  }

  /**
   * Checks if the query parameter is disallowed.
   *
   * @param \Drupal\helfi_react_search\Plugin\Validation\Constraint\LinkedEventsQueryStringConstraint $constraint
   *   The constraint to check.
   * @param string $queryParameter
   *   The query parameter to check.
   *
   * @return bool
   *   True if the query parameter is disallowed, false otherwise.
   */
  private function isDisallowedQueryParameter(LinkedEventsQueryStringConstraint $constraint, string $queryParameter): bool {
    foreach ($constraint->disallowedQueryParameters as $disallowedQueryParameter) {
      if ($queryParameter === $disallowedQueryParameter) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
