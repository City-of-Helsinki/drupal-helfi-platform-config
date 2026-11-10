<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_calculator\FunctionalJavascript;

/**
 * Shared helpers for the long-term care fee calculators.
 */
trait LongTermFeeTestTrait {

  /**
   * Builds the income field values.
   *
   * @param string[] $values
   *   The values in the order of the $fields.
   * @param string[] $fields
   *   The field ids.
   * @param string $prefix
   *   The field id prefix, 'spouse_' for the spouse fields.
   *
   * @return array<string, string>
   *   The values keyed by field id.
   */
  protected function incomeFields(array $values, array $fields, string $prefix = ''): array {
    return array_combine(array_map(static fn (string $field) => $prefix . $field, $fields), $values);
  }

  /**
   * Asserts that the guardianship fees maximum comes from the settings.
   */
  protected function assertGuardianshipFeesMaximum(): void {
    $this->openCalculator();

    foreach (['-1', '100'] as $value) {
      $this->fill('guardianship_fees', $value);
      $this->submit();
      $this->assertFieldError('guardianship_fees', '43.34');
    }
  }

}
