<?php

declare(strict_types=1);

namespace Drupal\helfi_calculator\Entity;

use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Bundle class for calculator paragraph.
 */
class Calculator extends Paragraph implements ParagraphInterface {

  /**
   * Get the calculator name.
   *
   * @return string
   *   The calculator name.
   *
   * @throws \LogicException
   *   Throws an exception if missing the field_calculator value.
   */
  public function getCalculatorName(): string {
    $value = $this->get('field_calculator')->value ?? '';
    if ($value === '') {
      throw new \LogicException('Calculator paragraph is missing field_calculator value.');
    }
    return $value;
  }

  /**
   * Get calculator instance ID.
   */
  public function getCalculatorInstanceId(): string {
    return 'helfi-calculator-' . $this->uuid();
  }

  /**
   * Get library name.
   */
  public function getCalculatorLibraryName(): string {
    return 'helfi_calculator/' . $this->getCalculatorName();
  }

  /**
   * Get calculator base libraries.
   */
  public function getBaseLibraries(): array {
    return [
      'helfi_calculator/helfi_calculator.base',
      'helfi_calculator/helfi_calculator.loader',
    ];
  }

}
