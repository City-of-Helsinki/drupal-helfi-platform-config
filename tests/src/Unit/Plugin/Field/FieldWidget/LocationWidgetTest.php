<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\Plugin\Field\FieldWidget;

use PHPUnit\Framework\Error\Warning;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\helfi_platform_config\Plugin\Field\FieldWidget\LocationWidget;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * Tests the LocationWidget field widget implementation.
 *
 * Test coverage includes:
 * - Form element creation with default values and structure
 * - Error element handling for valid location fields
 * - Error handling for invalid property access
 * - Form value processing and validation.
 *
 * @coversDefaultClass \Drupal\helfi_platform_config\Plugin\Field\FieldWidget\LocationWidget
 * @group helfi_platform_config
 */
class LocationWidgetTest extends UnitTestCase {

  /**
   * The LocationWidget instance under test.
   *
   * @var \Drupal\helfi_platform_config\Plugin\Field\FieldWidget\LocationWidget
   */
  protected $locationWidget;

  /**
   * The mocked field definition.
   *
   * @var \Drupal\Core\Field\FieldDefinitionInterface|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $fieldDefinition;

  /**
   * Sets up the test environment.
   */
  protected function setUp(): void {
    parent::setUp();
    $this->fieldDefinition = $this->prophesize(FieldDefinitionInterface::class);
    $this->locationWidget = new LocationWidget(
      'location',
      [],
      $this->fieldDefinition->reveal(),
      [],
      []
    );
  }

  /**
   * Creates a test form element with latitude and longitude fields.
   *
   * @return array
   *   A form element array with latitude and longitude number fields.
   */
  protected function createTestFormElement(): array {
    return [
      'latitude' => [
        '#type' => 'number',
        '#title' => 'Latitude',
      ],
      'longitude' => [
        '#type' => 'number',
        '#title' => 'Longitude',
      ],
    ];
  }

  /**
   * Tests form element creation with default values and structure.
   *
   * @covers ::formElement
   * @covers ::__construct
   */
  public function testFormElementCreation(): void {
    $items = $this->prophesize(FieldItemListInterface::class);
    $delta = 0;
    $form_state = $this->prophesize(FormStateInterface::class)->reveal();

    // Test coordinates for Helsinki city center.
    $item = new \stdClass();
    $item->latitude = 60.192059;
    $item->longitude = 24.945831;

    $items->offsetExists($delta)->willReturn(TRUE);
    $items->offsetGet($delta)->willReturn($item);

    $form = [];
    $element = [];

    $result = $this->locationWidget->formElement(
      $items->reveal(),
      $delta,
      $element,
      $form,
      $form_state
    );

    // Assert form element structure.
    $this->assertArrayHasKey('latitude', $result, 'Latitude field should exist');
    $this->assertArrayHasKey('longitude', $result, 'Longitude field should exist');

    // Assert wrapper attributes.
    $this->assertArrayHasKey('#attributes', $result, 'Wrapper should have attributes');
    $this->assertArrayHasKey('class', $result['#attributes'], 'Wrapper should have class attribute');
    $this->assertContains('location-elements', $result['#attributes']['class'], 'Wrapper should have location-elements class');

    // Assert field configurations.
    foreach (['latitude', 'longitude'] as $field) {
      $this->assertEquals('number', $result[$field]['#type'], "$field should be a number field");
      $this->assertSame('any', $result[$field]['#step'], "$field should allow any decimal value");
    }

    // Assert default values.
    $this->assertEquals(60.192059, $result['latitude']['#default_value'], 'Latitude default value should match');
    $this->assertEquals(24.945831, $result['longitude']['#default_value'], 'Longitude default value should match');
  }

  /**
   * Tests error element handling for valid location fields.
   *
   * @covers ::errorElement
   * @covers \Drupal\Core\Field\WidgetBase::errorElement
   */
  public function testErrorElementHandlesValidProperties(): void {
    $element = $this->createTestFormElement();
    $form_state = $this->prophesize(FormStateInterface::class);

    $error = $this->prophesize(ConstraintViolationInterface::class);
    $error->getPropertyPath()->willReturn('0.latitude');

    $result = $this->locationWidget->errorElement(
      $element,
      $error->reveal(),
      [],
      $form_state->reveal()
    );

    // Assert the correct element is returned for the error.
    $this->assertSame(
      $element['latitude'],
      $result,
      'Should return latitude element for errors'
    );
  }

  /**
   * Tests form value processing.
   *
   * @covers ::massageFormValues
   * @dataProvider massageFormValuesProvider
   */
  public function testMassageFormValues(array $input, array $expected): void {
    $form = [];
    $form_state = $this->prophesize(FormStateInterface::class)->reveal();

    $result = $this->locationWidget->massageFormValues($input, $form, $form_state);
    $this->assertEquals($expected, $result);
  }

  /**
   * Data provider for testMassageFormValues().
   *
   * @return array
   *   An array of test cases with input and expected output.
   *
   * @phpstan-return array<string, array{input: array, expected: array}>
   */
  public function massageFormValuesProvider(): array {
    return [
      'empty values become null' => [
        'input' => [
          [
            'latitude' => '',
            'longitude' => '',
          ],
        ],
        'expected' => [
          [
            'latitude' => NULL,
            'longitude' => NULL,
          ],
        ],
      ],
      'valid values remain unchanged' => [
        'input' => [
          [
            'latitude' => '60.192059',
            'longitude' => '24.945831',
          ],
        ],
        'expected' => [
          [
            'latitude' => '60.192059',
            'longitude' => '24.945831',
          ],
        ],
      ],
      'mixed values' => [
        'input' => [
          [
            'latitude' => '',
            'longitude' => '24.945831',
          ],
          [
            'latitude' => '60.192059',
            'longitude' => '',
          ],
        ],
        'expected' => [
          [
            'latitude' => NULL,
            'longitude' => NULL,
          ],
          [
            'latitude' => NULL,
            'longitude' => NULL,
          ],
        ],
      ],
      'null values' => [
        'input' => [
          [
            'latitude' => NULL,
            'longitude' => '24.945831',
          ],
        ],
        'expected' => [
          [
            'latitude' => NULL,
            'longitude' => '24.945831',
          ],
        ],
      ],
      'one empty value' => [
        'input' => [
          [
            'latitude' => '60.192059',
            'longitude' => '24.945831',
          ],
          [
            'latitude' => '',
            'longitude' => '24.945831',
          ],
        ],
        'expected' => [
          [
            'latitude' => '60.192059',
            'longitude' => '24.945831',
          ],
          [
            'latitude' => NULL,
            'longitude' => NULL,
          ],
        ],
      ],
    ];
  }

}
