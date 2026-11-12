<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_calculator\FunctionalJavascript;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the families home services client fee calculator.
 */
#[Group('helfi_calculator')]
#[RunTestsInSeparateProcesses]
final class FamiliesHomeServicesClientFeeTest extends CalculatorTestBase {

  /**
   * {@inheritdoc}
   */
  protected string $calculator = 'families_home_services_client_fee';

  /**
   * Tests the calculated fee.
   */
  public function testResults(): void {
    $cases = [
      'Low' => ['household' => '2', 'income' => '2000', 'usage' => '5', 'result' => '15,90'],
      'Medium' => ['household' => '3', 'income' => '4500', 'usage' => '10', 'result' => '78,27'],
      'High' => ['household' => '6', 'income' => '8000', 'usage' => '23', 'result' => '284,94'],
    ];

    $this->runCases($cases, function (array $case, string $name): void {
      $this->fill('household_size', $case['household']);
      $this->fill('gross_income_per_month', $case['income']);
      $this->fill('monthly_usage', $case['usage']);
      $this->submit();
      $this->assertTotal($case['result'], $name);
    });
  }

}
