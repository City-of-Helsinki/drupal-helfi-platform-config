<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_calculator\FunctionalJavascript;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the home care service voucher calculator.
 */
#[Group('helfi_calculator')]
#[RunTestsInSeparateProcesses]
final class HomeCareServiceVoucherTest extends CalculatorTestBase {

  /**
   * {@inheritdoc}
   */
  protected string $calculator = 'home_care_service_voucher';

  /**
   * {@inheritdoc}
   */
  protected array $dependencies = ['home_care_client_fee'];

  /**
   * Tests the calculated client payment and the receipt subtotals.
   */
  public function testResults(): void {
    // Subtotals: total service cost, city payment, service voucher value per
    // hour and city home care cost.
    $cases = [
      'Low (1, 500€, 10h, 34€/h)' => [
        'input' => ['1', '500', '10', '34'],
        'result' => '0,00',
        'subtotals' => ['340,00', '340,00', '40,80', '0,00'],
      ],
      'Low (1, 800€, 10h, 38€/h)' => [
        'input' => ['1', '800', '10', '38'],
        'result' => '0,00',
        'subtotals' => ['380,00', '380,00', '40,09', '17,17'],
      ],
      'Low (1, 2000€, 10h, 34€/h)' => [
        'input' => ['1', '2000', '10', '34'],
        'result' => '23,10',
        'subtotals' => ['340,00', '316,90', '31,69', '221,17'],
      ],
      'Medium (2, 4400€, 10h, 34€/h)' => [
        'input' => ['2', '4400,00', '10', '34'],
        'result' => '68,80',
        'subtotals' => ['340,00', '271,20', '27,12', '435,40'],
      ],
      'Medium (2, 3300,10€, 22h, 48€/h)' => [
        'input' => ['2', '3300,10', '22', '48'],
        'result' => '352,88',
        'subtotals' => ['1056,00', '703,12', '31,96', '442,22'],
      ],
      'High (1, 5975,51€, 62h, 49€/h)' => [
        'input' => ['1', '5975,51', '62', '49'],
        'result' => '2517,20',
        'subtotals' => ['3038,00', '520,80', '8,40', '1846,78'],
      ],
    ];

    $this->runCases($cases, function (array $case, string $name): void {
      $this->fillAll(array_combine(
        ['household_size', 'gross_income_per_month', 'monthly_usage', 'service_provider_price'],
        $case['input'],
      ));
      $this->submit();
      $this->assertTotal($case['result'], $name);
      $this->assertSame($case['subtotals'], array_slice($this->getSubtotals(), 0, 4), $name);
    });
  }

}
