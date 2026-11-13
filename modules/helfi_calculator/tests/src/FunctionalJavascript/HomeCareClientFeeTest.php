<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_calculator\FunctionalJavascript;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the home care client fee calculator.
 */
#[Group('helfi_calculator')]
#[RunTestsInSeparateProcesses]
final class HomeCareClientFeeTest extends CalculatorTestBase {

  /**
   * {@inheritdoc}
   */
  protected string $calculator = 'home_care_client_fee';

  /**
   * Tests the calculated fee with each combination of extra services.
   */
  public function testResults(): void {
    $profiles = [
      'Low' => [
        'input' => ['1', '1000', '20'],
        // Keyed by "<safety phone>|<grocery delivery>|<meals per week>".
        'results' => [
          'yes|yes|0' => '149,52',
          'yes|yes|4' => '233,52',
          'yes|yes|7' => '296,52',
          'no|yes|0' => '113,84',
          'no|yes|4' => '197,84',
          'no|yes|7' => '260,84',
          'no|no|0' => '72,24',
          'no|no|4' => '156,24',
          'no|no|7' => '219,24',
        ],
      ],
      'Medium' => [
        'input' => ['2', '3000', '30'],
        'results' => [
          'yes|yes|0' => '523,36',
          'yes|yes|4' => '607,36',
          'yes|yes|7' => '670,36',
          'no|yes|0' => '452,00',
          'no|yes|4' => '536,00',
          'no|yes|7' => '599,00',
          'no|no|0' => '410,40',
          'no|no|4' => '494,40',
          'no|no|7' => '557,40',
        ],
      ],
      'High' => [
        'input' => ['4', '7000', '50'],
        'results' => [
          'yes|yes|0' => '832,48',
          'yes|yes|4' => '916,48',
          'yes|yes|7' => '979,48',
          'no|yes|0' => '761,12',
          'no|yes|4' => '845,12',
          'no|yes|7' => '908,12',
          'no|no|0' => '719,52',
          'no|no|4' => '803,52',
          'no|no|7' => '866,52',
        ],
      ],
    ];

    $cases = [];
    foreach ($profiles as $profile => $data) {
      foreach ($data['results'] as $options => $result) {
        [$phone, $grocery, $meals] = explode('|', $options);
        $cases["$profile: phone $phone, grocery $grocery, meals $meals"] = [
          'input' => $data['input'],
          'phone' => $phone === 'yes',
          'grocery' => $grocery === 'yes',
          'meals' => (int) $meals,
          'result' => $result,
        ];
      }
    }

    $this->runCases($cases, function (array $case, string $name): void {
      $this->fillAll(array_combine(['household_size', 'gross_income_per_month', 'monthly_usage'], $case['input']));
      // Radio values: 1 = yes, 2 = no.
      $this->selectRadio('safetyphone', $case['phone'] ? 1 : 2);
      $this->selectRadio('shopping_service', $case['grocery'] ? 1 : 2);
      $this->selectRadio('meal_service', $case['meals'] ? 1 : 2);
      if ($case['meals']) {
        $this->fill('meal_service_per_week', $case['meals']);
      }
      $this->submit();
      $this->assertTotal($case['result'], $name);
    });
  }

}
