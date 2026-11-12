<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_calculator\FunctionalJavascript;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Helsinki benefit amount estimate calculator.
 */
#[Group('helfi_calculator')]
#[RunTestsInSeparateProcesses]
final class HelsinkiBenefitAmountEstimateTest extends CalculatorTestBase {

  /**
   * {@inheritdoc}
   */
  protected string $calculator = 'helsinki_benefit_amount_estimate';

  /**
   * Tests the estimated benefit for each employer type.
   */
  public function testResults(): void {
    // With the current settings every employer type gets the same 50% state
    // aid percentage, so the result does not depend on the employer type.
    $profiles = [
      'Low' => ['pay' => '400', 'vacation_money' => '16', 'result' => '252,00'],
      'Medium' => ['pay' => '1000', 'vacation_money' => '42', 'result' => '631,00'],
      'High' => ['pay' => '2000', 'vacation_money' => '88', 'result' => '1264,00'],
    ];

    // Employer type and whether the association has business activities.
    $employers = [
      'business' => ['business', FALSE],
      'association with business activities' => ['association', TRUE],
      'association' => ['association', FALSE],
    ];

    $cases = [];
    foreach ($profiles as $profile => $data) {
      foreach ($employers as $employer => [$companyType, $businessActivities]) {
        $cases["$profile, $employer"] = [
          'pay' => $data['pay'],
          'vacation_money' => $data['vacation_money'],
          'company_type' => $companyType,
          'business_activities' => $businessActivities,
          'result' => $data['result'],
        ];
      }
    }

    $this->runCases($cases, function (array $case, string $name): void {
      $this->fill('monthly_pay', $case['pay']);
      $this->fill('vacation_money', $case['vacation_money']);
      $this->selectRadio('company_type', $case['company_type']);
      if ($case['business_activities']) {
        $this->setCheckbox('association_has_business_activities');
      }
      // The checkbox confirms that no other aid has been granted.
      $this->setCheckbox('pay_subsidy_granted');
      $this->submit();
      $this->assertTotal($case['result'], $name);
    });
  }

}
