<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_calculator\FunctionalJavascript;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the long-term assisted living fee calculator.
 */
#[Group('helfi_calculator')]
#[RunTestsInSeparateProcesses]
final class LongTermAssistedLivingFeeTest extends CalculatorTestBase {

  use LongTermFeeTestTrait;

  /**
   * {@inheritdoc}
   */
  protected string $calculator = 'long_term_assisted_living_fee';

  /**
   * The income fields, in the order of the case values.
   */
  private const array FIELDS = [
    'earned_income',
    'client_benefits',
    'capital_income',
    'annual_forest_income',
    'guardianship_fees',
    'client_foreclosure',
    'compensation_or_life_annuity',
    'maintenance_payments',
    'medication_costs',
    'share_of_housing_costs',
  ];

  /**
   * Tests the calculator.
   */
  public function testCalculator(): void {
    $this->assertResults();
    $this->assertGuardianshipFeesMaximum();
  }

  /**
   * Asserts the calculated fee with and without a spouse.
   */
  private function assertResults(): void {
    // Values in the order of self::FIELDS.
    $profiles = [
      'Client low income' => [
        'client' => ['1500', '300', '0', '1200', '0', '0', '0', '500', '100', '200'],
        'spouse' => ['8000', '600', '0', '1000', '0', '500', '0', '500', '200', '300'],
        'spouse_yes' => '841,99',
        'spouse_no' => '841,99',
      ],
      'Same income' => [
        'client' => ['3000', '300', '0', '1200', '0', '0', '0', '500', '100', '200'],
        'spouse' => ['3000', '300', '0', '1200', '0', '0', '0', '500', '100', '200'],
        'spouse_yes' => '2156,44',
        'spouse_no' => '2156,44',
      ],
      'Client high income' => [
        'client' => ['3000', '600', '0', '1000', '0', '0', '0', '500', '200', '300'],
        'spouse' => ['1000', '600', '0', '1000', '0', '0', '0', '500', '200', '300'],
        'spouse_yes' => '1401,22',
        'spouse_no' => '2228,69',
      ],
      'Spouse no income' => [
        'client' => ['1500', '300', '0', '1200', '0', '0', '0', '500', '100', '200'],
        'spouse' => ['0', '0', '0', '0', '0', '0', '0', '0', '0', '0'],
        'spouse_yes' => '263,56',
        'spouse_no' => '841,99',
      ],
    ];

    $cases = [];
    foreach ($profiles as $profile => $data) {
      $cases["$profile, spouse yes"] = [
        'client' => $data['client'],
        'spouse' => $data['spouse'],
        'result' => $data['spouse_yes'],
      ];
      $cases["$profile, spouse no"] = [
        'client' => $data['client'],
        'spouse' => NULL,
        'result' => $data['spouse_no'],
      ];
    }

    $this->runCases($cases, function (array $case, string $name): void {
      $this->fillAll($this->incomeFields($case['client'], self::FIELDS));
      $this->selectRadio('has_spouse', $case['spouse'] ? 'true' : 'false');
      if ($case['spouse']) {
        $this->fillAll($this->incomeFields($case['spouse'], self::FIELDS, 'spouse_'));
      }
      $this->submit();
      $this->assertTotal($case['result'], $name);
    });
  }

}
