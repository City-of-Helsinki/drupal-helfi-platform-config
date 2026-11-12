<?php

declare(strict_types=1);

namespace Drupal\helfi_calculator_test\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Renders a single calculator with settings stored in state.
 */
final class CalculatorTestController extends ControllerBase {

  /**
   * The calculator element id.
   */
  public const string ELEMENT_ID = 'helfi-calculator-test';

  /**
   * The state key holding calculator settings JSON, keyed by calculator name.
   */
  public const string STATE_KEY = 'helfi_calculator_test.settings';

  /**
   * Builds the calculator page.
   *
   * @param string $calculator
   *   The calculator machine name, e.g. 'home_care_client_fee'.
   *
   * @return array<string, mixed>
   *   The render array.
   */
  public function build(string $calculator): array {
    $settings = $this->state()->get(self::STATE_KEY, []);

    if (!isset($settings[$calculator])) {
      throw new NotFoundHttpException();
    }

    // Like helfi_calculator, expose every calculator's settings by name. Some
    // calculators read another calculator's settings, e.g. the home care
    // service voucher uses the home care client fee settings.
    $drupalSettings = $settings;
    $drupalSettings[self::ELEMENT_ID] = [
      'id' => self::ELEMENT_ID,
      'type' => $calculator,
      'data' => $settings[$calculator],
    ];

    return [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'id' => self::ELEMENT_ID,
        'class' => ['js-helfi-calculator'],
        'data-calculator' => $calculator,
        'data-settings-key' => self::ELEMENT_ID,
      ],
      '#attached' => [
        'library' => [
          'helfi_calculator_test/helfi_calculator.base',
          'helfi_calculator_test/helfi_calculator.loader',
          'helfi_calculator_test/' . $calculator,
        ],
        'drupalSettings' => [
          'helfiCalculator' => $drupalSettings,
        ],
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

}
