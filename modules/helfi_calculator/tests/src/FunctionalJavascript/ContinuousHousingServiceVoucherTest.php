<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_calculator\FunctionalJavascript;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the continuous housing service voucher calculator.
 */
#[Group('helfi_calculator')]
#[RunTestsInSeparateProcesses]
final class ContinuousHousingServiceVoucherTest extends CalculatorTestBase {

  /**
   * {@inheritdoc}
   */
  protected string $calculator = 'continuous_housing_service_voucher';

  /**
   * Tests the calculated voucher.
   */
  public function testResults(): void {
    $cases = [
      'Low' => ['income' => '0', 'price' => '120', 'result' => '119,00'],
      'Medium' => ['income' => '2000', 'price' => '180', 'result' => '3121,00'],
      'High' => ['income' => '9000', 'price' => '210', 'result' => '5852,50'],
    ];

    $this->runCases($cases, function (array $case, string $name): void {
      $this->fill('net_income_per_month', $case['income']);
      $this->fill('service_provider_price', $case['price']);
      $this->submit();
      $this->assertTotal($case['result'], $name);
    });
  }

}
