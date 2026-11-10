<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_calculator\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\helfi_calculator_test\Controller\CalculatorTestController;

/**
 * Base class for calculator algorithm tests.
 */
abstract class CalculatorTestBase extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_calculator_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The calculator machine name, e.g. 'home_care_client_fee'.
   */
  protected string $calculator;

  /**
   * Other calculators whose settings the calculator reads.
   *
   * @var string[]
   */
  protected array $dependencies = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $settings = [];
    foreach ([$this->calculator, ...$this->dependencies] as $calculator) {
      $fixture = dirname(__DIR__, 2) . '/fixtures/' . $calculator . '.json';
      $this->assertFileExists($fixture);
      $settings[$calculator] = file_get_contents($fixture);
    }

    $this->container->get(StateInterface::class)->set(CalculatorTestController::STATE_KEY, $settings);
  }

  /**
   * Opens a fresh calculator form.
   */
  protected function openCalculator(): void {
    $this->drupalGet(Url::fromRoute('helfi_calculator_test.calculator', ['calculator' => $this->calculator]));
    $this->assertNotNull(
      $this->assertSession()->waitForElement('css', '#' . CalculatorTestController::ELEMENT_ID . ' form.helfi-calculator'),
      'Calculator form was not rendered.',
    );
  }

  /**
   * Gets a calculator element by its id.
   *
   * @param string $id
   *   The element id without the form id suffix.
   */
  protected function getElement(string $id): NodeElement {
    $selector = '#' . $id . '_' . CalculatorTestController::ELEMENT_ID;
    $element = $this->getSession()->getPage()->find('css', $selector);
    $this->assertNotNull($element, "Element $selector not found.");
    return $element;
  }

  /**
   * Fills a text input.
   *
   * @param string $id
   *   The field id.
   * @param string|int $value
   *   The value.
   */
  protected function fill(string $id, string|int $value): void {
    $this->getElement($id)->setValue((string) $value);
  }

  /**
   * Fills several text inputs.
   *
   * @param array<string, string|int> $values
   *   The values keyed by field id.
   */
  protected function fillAll(array $values): void {
    foreach ($values as $id => $value) {
      $this->fill($id, $value);
    }
  }

  /**
   * Selects a radio button.
   *
   * @param string $id
   *   The radio group id.
   * @param string|int $value
   *   The value of the radio button to select.
   */
  protected function selectRadio(string $id, string|int $value): void {
    $radio = $this->getElement($id)->find('css', 'input[type="radio"][value="' . $value . '"]');
    $this->assertNotNull($radio, "Radio $id with value $value not found.");
    $radio->click();
    $this->assertTrue($radio->isChecked(), "Radio $id with value $value is not checked.");
  }

  /**
   * Sets a checkbox state.
   *
   * @param string $id
   *   The checkbox id.
   * @param bool $checked
   *   Whether the checkbox should be checked.
   */
  protected function setCheckbox(string $id, bool $checked = TRUE): void {
    $checkbox = $this->getElement($id);
    if ($checkbox->isChecked() !== $checked) {
      $checkbox->click();
    }
    $this->assertSame($checked, $checkbox->isChecked(), "Checkbox $id state is wrong.");
  }

  /**
   * Submits the calculator form.
   */
  protected function submit(): void {
    $this->getSession()->getPage()
      ->find('css', '#' . CalculatorTestController::ELEMENT_ID . ' input[type="submit"]')
      ->click();
  }

  /**
   * Gets the text of the error notification.
   */
  protected function getErrorText(): string {
    $element = $this->getSession()->getPage()
      ->find('css', '#' . CalculatorTestController::ELEMENT_ID . ' .helfi-calculator-notification--error');
    return trim($element?->getText() ?? '');
  }

  /**
   * Asserts the receipt total.
   *
   * @param string $expected
   *   The expected total, e.g. '149,52'.
   * @param string $message
   *   The case description, shown on failure.
   */
  protected function assertTotal(string $expected, string $message = ''): void {
    $total = $this->assertSession()->waitForElement('css', '.helfi-calculator__receipt-total__value');
    $this->assertNotNull($total, trim("$message: no result. Errors: " . $this->getErrorText(), ': '));
    $this->assertSame($expected, trim($total->getText()), $message);
  }

  /**
   * Gets the receipt subtotal values, without units.
   *
   * @return string[]
   *   The subtotal amounts, e.g. ['340,00', '40,80'].
   */
  protected function getSubtotals(): array {
    $elements = $this->getSession()->getPage()
      ->findAll('css', '.helfi-calculator__receipt-subtotal-sum > span[aria-hidden="true"]');
    return array_map(
      static fn (NodeElement $element) => explode(' ', trim($element->getText()))[0],
      $elements,
    );
  }

  /**
   * Asserts that a field has a validation error containing the given text.
   *
   * @param string $id
   *   The field id.
   * @param string $expected
   *   Text the error message should contain.
   */
  protected function assertFieldError(string $id, string $expected): void {
    $selector = '#error_text_' . $id . '_' . CalculatorTestController::ELEMENT_ID;
    $this->assertNotNull(
      $this->assertSession()->waitForElement('css', $selector . ':not(:empty)'),
      "No error for $id.",
    );
    $this->assertStringContainsString($expected, $this->getSession()->getPage()->find('css', $selector)->getText());
  }

  /**
   * Runs each case on a fresh calculator form.
   *
   * Running all cases in one test method avoids reinstalling Drupal for every
   * case.
   *
   * @param array<string, mixed> $cases
   *   The cases, keyed by name.
   * @param callable $run
   *   Callback receiving the case and its name; fills the form and asserts.
   */
  protected function runCases(array $cases, callable $run): void {
    foreach ($cases as $name => $case) {
      $this->openCalculator();
      $run($case, (string) $name);
    }
  }

}
