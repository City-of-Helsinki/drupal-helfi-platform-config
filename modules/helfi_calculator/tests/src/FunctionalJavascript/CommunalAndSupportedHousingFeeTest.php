<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_calculator\FunctionalJavascript;

use Drupal\helfi_calculator_test\Controller\CalculatorTestController;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the communal and supported housing fee calculator.
 */
#[Group('helfi_calculator')]
#[RunTestsInSeparateProcesses]
final class CommunalAndSupportedHousingFeeTest extends CalculatorTestBase {

  /**
   * {@inheritdoc}
   */
  protected string $calculator = 'communal_and_supported_housing_fee';

  /**
   * Tests the calculator.
   */
  public function testCalculator(): void {
    $this->assertResults();
    $this->assertSafetyPhonePrices();
    $this->assertGuardianshipFeesMaximum();
  }

  /**
   * Asserts the calculated fee with each combination of extra services.
   */
  private function assertResults(): void {
    $profiles = [
      'Low' => [
        'input' => ['1', '1000', '20'],
        'guardianship' => '20',
        // Keyed by "<safety phone>|<grocery delivery>|<meal service>", with
        // "+guardianship" when guardianship fees are filled in.
        'results' => [
          'yes|yes|none' => '149,52',
          'yes|yes|partial' => '548,92',
          'yes|yes|full' => '681,92',
          'no|yes|none' => '113,84',
          'no|yes|none+guardianship' => '109,04',
          'no|yes|full' => '646,24',
          'no|yes|partial' => '513,24',
          'no|no|none' => '72,24',
          'no|no|full' => '604,64',
          'no|no|partial' => '471,64',
        ],
      ],
      'Medium' => [
        'input' => ['2', '3000', '30'],
        'guardianship' => '40',
        'results' => [
          'yes|yes|none' => '523,36',
          'yes|yes|partial' => '922,76',
          'yes|yes|full' => '1055,76',
          'no|yes|none' => '452,00',
          'no|yes|none+guardianship' => '442,40',
          'no|yes|full' => '984,40',
          'no|yes|partial' => '851,40',
          'no|no|none' => '410,40',
          'no|no|full' => '942,80',
          'no|no|partial' => '809,80',
        ],
      ],
      'High' => [
        'input' => ['4', '7000', '50'],
        'guardianship' => '30',
        'results' => [
          'yes|yes|none' => '832,48',
          'yes|yes|partial' => '1231,88',
          'yes|yes|full' => '1364,88',
          'no|yes|none' => '761,12',
          'no|yes|none+guardianship' => '756,32',
          'no|yes|full' => '1293,52',
          'no|yes|partial' => '1160,52',
          'no|no|none' => '719,52',
          'no|no|full' => '1251,92',
          'no|no|partial' => '1118,92',
        ],
      ],
    ];

    $mealValues = [
      'none' => 'false',
      'partial' => 'partial_service',
      'full' => 'full_service',
    ];

    $cases = [];
    foreach ($profiles as $profile => $data) {
      foreach ($data['results'] as $options => $result) {
        [$options, $guardianship] = array_pad(explode('+', $options), 2, NULL);
        [$phone, $grocery, $meal] = explode('|', $options);
        $cases["$profile: $options" . ($guardianship ? ' + guardianship fees' : '')] = [
          'input' => $data['input'],
          'guardianship' => $guardianship ? $data['guardianship'] : NULL,
          'phone' => $phone === 'yes' ? 'true' : 'false',
          'grocery' => $grocery === 'yes' ? 'true' : 'false',
          'meal' => $mealValues[$meal],
          'result' => $result,
        ];
      }
    }

    $this->runCases($cases, function (array $case, string $name): void {
      $this->fillAll(array_combine(['household_size', 'gross_income_per_month', 'monthly_usage'], $case['input']));
      if ($case['guardianship'] !== NULL) {
        $this->fill('guardianship_fees', $case['guardianship']);
      }
      $this->selectRadio('safety_phone_and_bracelet', $case['phone']);
      $this->selectRadio('grocery_delivery_service', $case['grocery']);
      $this->selectRadio('meal_service', $case['meal']);
      $this->submit();
      $this->assertTotal($case['result'], $name);
    });
  }

  /**
   * Asserts that the safety phone helper text shows the configured prices.
   */
  private function assertSafetyPhonePrices(): void {
    $this->openCalculator();
    $this->selectRadio('safety_phone_and_bracelet', 'true');

    $text = $this->getElement('safetyphone_group')->getText();
    foreach (['23,25', '46,50', '116,25', '232,50'] as $price) {
      $this->assertStringContainsString($price, $text);
    }
  }

  /**
   * Asserts that the guardianship fees maximum comes from the settings.
   */
  private function assertGuardianshipFeesMaximum(): void {
    $this->openCalculator();

    foreach (['-1', '100'] as $value) {
      $this->fill('guardianship_fees', $value);
      $this->submit();
      $this->assertFieldError('guardianship_fees', '43.34');
    }

    // A value within the range is accepted.
    $this->fill('guardianship_fees', '43');
    $this->submit();
    $this->assertSession()->elementTextNotContains(
      'css',
      '#error_text_guardianship_fees_' . CalculatorTestController::ELEMENT_ID,
      '43.34',
    );
  }

}
