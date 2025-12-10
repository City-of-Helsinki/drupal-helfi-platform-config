<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_calculator\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\helfi_calculator\Entity\Calculator;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests Calculator paragraph entity.
 *
 * @group helfi_calculator
 */
final class CalculatorParagraphTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_reference_revisions',
    'field',
    'file',
    'helfi_calculator',
    'paragraphs',
    'system',
    'text',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('field_storage_config');
    $this->installEntitySchema('field_config');

    ParagraphsType::create([
      'id' => 'calculator',
      'label' => 'Calculator',
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_calculator',
      'entity_type' => 'paragraph',
      'type' => 'string',
      'cardinality' => 1,
      'settings' => [
        'max_length' => 255,
        'is_ascii' => FALSE,
        'case_sensitive' => FALSE,
      ],
      'translatable' => FALSE,
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_calculator',
      'entity_type' => 'paragraph',
      'bundle' => 'calculator',
      'label' => 'Calculator',
    ])->save();
  }

  /**
   * Helper to create a fresh Calculator paragraph entity.
   */
  private function createCalculatorParagraph(?string $machine_name = NULL): Calculator {
    /** @var \Drupal\helfi_calculator\Entity\Calculator $paragraph */
    $paragraph = Paragraph::create([
      'type' => 'calculator',
    ]);
    $this->assertInstanceOf(Calculator::class, $paragraph);

    if ($machine_name !== NULL) {
      $paragraph->set('field_calculator', $machine_name);
    }

    $paragraph->save();
    return $paragraph;
  }

  /**
   * Test the getCalculatorName() exception.
   */
  public function testGetCalculatorNameThrowsWhenMissing(): void {
    $paragraph = $this->createCalculatorParagraph();

    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('Calculator paragraph is missing field_calculator value.');
    $paragraph->getCalculatorName();
  }

  /**
   * Test the getters.
   */
  #[DataProvider('knownCalculatorProvider')]
  public function testGetters(string $machine_name): void {
    $paragraph = $this->createCalculatorParagraph($machine_name);

    $this->assertSame($machine_name, $paragraph->getCalculatorName());
    $this->assertSame('helfi-calculator-' . $paragraph->uuid(), $paragraph->getCalculatorInstanceId());
    $this->assertSame('helfi_calculator/' . $machine_name, $paragraph->getCalculatorLibraryName());
    $this->assertSame([
      'helfi_calculator/helfi_calculator.base',
      'helfi_calculator/helfi_calculator.loader',
    ], $paragraph->getBaseLibraries());
  }

  /**
   * Test the getCalculatorLibraryName() for known calculators.
   */
  #[DataProvider('knownCalculatorProvider')]
  public function testLibraryNamesForKnownCalculators(string $machine_name): void {
    $paragraph = $this->createCalculatorParagraph($machine_name);
    $this->assertSame('helfi_calculator/' . $machine_name, $paragraph->getCalculatorLibraryName());
  }

  /**
   * Provides known calculator machine names.
   *
   * @return array
   *   An array of calculator machine names.
   */
  public static function knownCalculatorProvider(): array {
    $items = [
      'continuous_housing_service_voucher',
      'early_childhood_education_fee',
      'families_home_services_client_fee',
      'helsinki_benefit_amount_estimate',
      'home_care_client_fee',
      'home_care_service_voucher',
    ];

    $data = [];
    foreach ($items as $item) {
      $data[$item] = [$item];
    }
    return $data;
  }

}
