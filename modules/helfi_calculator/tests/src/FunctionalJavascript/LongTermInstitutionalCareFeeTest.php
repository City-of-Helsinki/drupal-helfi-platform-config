<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_calculator\FunctionalJavascript;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the long-term institutional care fee calculator.
 */
#[Group('helfi_calculator')]
#[RunTestsInSeparateProcesses]
final class LongTermInstitutionalCareFeeTest extends CalculatorTestBase {

  use LongTermFeeTestTrait;

  /**
   * {@inheritdoc}
   */
  protected string $calculator = 'long_term_institutional_care_fee';

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
  ];

  /**
   * Tests the calculator.
   */
  public function testCalculator(): void {
    $this->assertResults();
    $this->assertGuardianshipFeesMaximum();
  }

  /**
   * Asserts the fee for each social welfare act and spouse combination.
   */
  private function assertResults(): void {
    // Values in the order of self::FIELDS. Results are keyed by
    // "<social welfare act>|<has spouse>".
    $profiles = [
      'Client low income' => [
        'client' => ['1500', '300', '0', '1200', '0', '0', '0', '500'],
        'spouse' => ['3000', '600', '0', '1000', '0', '500', '0', '500'],
        'results' => [
          'yes|yes' => '1181,50',
          'no|yes' => '1181,50',
          'yes|no' => '1181,50',
          'no|no' => '1181,50',
        ],
      ],
      'Same income' => [
        'client' => ['2000', '300', '0', '1200', '0', '0', '0', '500'],
        'spouse' => ['2000', '300', '0', '1200', '0', '0', '0', '500'],
        'results' => [
          'yes|yes' => '1606,50',
          'no|yes' => '1606,50',
          'yes|no' => '1606,50',
          'no|no' => '1606,50',
        ],
      ],
      'Client high income' => [
        'client' => ['20000', '600', '0', '1000', '0', '0', '0', '500'],
        'spouse' => ['1000', '600', '0', '1000', '0', '0', '0', '500'],
        'results' => [
          'yes|yes' => '7656,00',
          'no|yes' => '9073,75',
          'yes|no' => '7656,00',
          'no|no' => '12045,00',
        ],
      ],
    ];

    $cases = [];
    foreach ($profiles as $profile => $data) {
      foreach ($data['results'] as $options => $result) {
        [$socialWelfare, $spouse] = explode('|', $options);
        $cases["$profile, social welfare act $socialWelfare, spouse $spouse"] = [
          'client' => $data['client'],
          'spouse' => $spouse === 'yes' ? $data['spouse'] : NULL,
          'social_welfare_act' => $socialWelfare === 'yes' ? 'true' : 'false',
          'result' => $result,
        ];
      }
    }

    $this->runCases($cases, function (array $case, string $name): void {
      $this->fillAll($this->incomeFields($case['client'], self::FIELDS));
      $this->selectRadio('social_welfare_act', $case['social_welfare_act']);
      $this->selectRadio('has_spouse', $case['spouse'] ? 'true' : 'false');
      if ($case['spouse']) {
        $this->fillAll($this->incomeFields($case['spouse'], self::FIELDS, 'spouse_'));
      }
      $this->submit();
      $this->assertTotal($case['result'], $name);
    });
  }

}
