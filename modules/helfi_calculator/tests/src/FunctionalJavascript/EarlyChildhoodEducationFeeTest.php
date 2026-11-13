<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_calculator\FunctionalJavascript;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the early childhood education fee calculator.
 *
 * Daycare types:
 * 1. Early childhood education on weekdays.
 * 2. Early childhood education for 5-year-olds on weekdays.
 * 3. Pre-school and early childhood education on weekdays.
 * 4. Round-the-clock care.
 *
 * Care times by daycare type:
 * 1. Over 7h / 5-7h / at most 5h per day.
 * 2. Over 7h / 5-7h / 4-5h / at most 4h per day (free).
 * 3. Over 7h / 7-8h / 5-7h / at most 5h per day.
 * 4. 161h or more / 101-160h / 61-100h per month.
 *
 * Only daycare type 4 has the pre-school flag, and only types 1-3 have
 * regular days off.
 */
#[Group('helfi_calculator')]
#[RunTestsInSeparateProcesses]
final class EarlyChildhoodEducationFeeTest extends CalculatorTestBase {

  /**
   * {@inheritdoc}
   */
  protected string $calculator = 'early_childhood_education_fee';

  /**
   * Tests the calculated fee.
   */
  public function testResults(): void {
    // Children are [daycare type, care time, free days, pre-school flag],
    // youngest first.
    $cases = [
      // Household size 2: income below / over minimum.
      'Household size 2, 1 child, income 2770 (below min)' => ['2', '2770', [[1, 1, 5]], '0'],
      'Household size 2, 1 child, income 4000 (calc < minimum payment)' => ['2', '4000', [[1, 1, 5]], '0'],
      'Household size 2, 1 child, income just above minimum' => ['2', '4664', [[1, 1, 0]], '32'],
      // Family sizes 4-8.
      'Household size 4, below min' => ['4', '5500', [[1, 1, 0], [2, 4, 0]], '0'],
      'Household size 4, above min, normal payment' => ['4', '7000', [[1, 1, 0], [2, 4, 0]], '64'],
      'Household size 8, below computed min threshold' => ['8', '7500', [[1, 1, 0], [2, 4, 0]], '0'],
      'Household size 8, above computed min threshold' => ['8', '8809', [[1, 1, 0], [2, 4, 0]], '0'],
      // Maximum charge.
      'Household size 2, high income, capped' => ['2', '8000', [[1, 1, 0]], '335'],
      // Sibling discounts (1st 100%, 2nd 40%, 3rd 20%).
      'Household size 4, 3 children, sibling discounts' => ['4', '10000', [[1, 1, 0], [1, 1, 0], [1, 1, 0]], '536'],
      // Daycare type and care time discounts.
      'Daycare type 1 over 7h (100%)' => ['4', '7000', [[1, 1, 0]], '64'],
      'Daycare type 1 5-7h (80%)' => ['4', '7000', [[1, 2, 0]], '51'],
      'Daycare type 1 at most 5h (60%)' => ['4', '7000', [[1, 3, 0]], '39'],
      'Daycare type 2 (5yo), at most 4h (0%)' => ['4', '7000', [[2, 4, 0]], '0'],
      'Daycare type 3 (6yo), 7-8h (60%)' => ['4', '7000', [[3, 2, 0]], '39'],
      'Daycare type 4, 161h+ per month, no pre-school (100%)' => ['4', '7000', [[4, 1, 0, FALSE]], '64'],
      'Daycare type 4, 101-160h per month, no pre-school (80%)' => ['4', '7000', [[4, 2, 0, FALSE]], '51'],
      'Daycare type 4, 61-100h per month (60%)' => ['4', '7000', [[4, 3, 0, FALSE]], '39'],
      'Daycare type 4 with pre-school (65%)' => ['4', '7000', [[4, 1, 0, TRUE]], '42'],
      // Free day discount.
      '3 children, no free days' => ['4', '7871', [[1, 1, 0], [1, 1, 0], [1, 1, 0]], '253'],
      '3 children, 4, 6 and 8 free days' => ['4', '7871', [[1, 1, 4], [1, 1, 6], [1, 1, 8]], '180'],
    ];

    $this->runCases($cases, function (array $case, string $name): void {
      [$householdSize, $income, $children, $result] = $case;

      $this->fill('household_size', $householdSize);
      $this->fill('gross_income_per_month', $income);

      foreach ($children as $index => [$type, $careTime, $freeDays]) {
        $child = $index + 1;
        $preschool = $children[$index][3] ?? NULL;

        if ($child > 1) {
          $this->getElement('add-button_nth_child')->click();
          $this->assertNotNull($this->assertSession()->waitForElement('css', '[data-slot-number="' . $child . '"]'));
        }

        $this->selectRadio("daycare_type_for_child_$child", $type);
        $this->selectRadio("daycare_type_{$type}_{$child}_group_caretime", $careTime);

        if ($type < 4) {
          $this->fill("daycare_type_{$type}_{$child}_free_days", $freeDays);
        }
        if ($type === 4 && $preschool !== NULL) {
          $this->setCheckbox("daycare_type_4_{$child}_has_preschool", $preschool);
        }
      }

      $this->submit();
      $this->assertTotal($result, $name);
    });
  }

}
