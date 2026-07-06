<?php

declare(strict_types=1);

namespace Drupal\Tests\hdbt_admin_tools\Unit\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormState;
use Drupal\hdbt_admin_tools\Plugin\Field\FieldWidget\TextfieldCharacterCounterWidget;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Prophecy\Argument;

/**
 * Tests the textfield character counter widget.
 */
#[Group('hdbt_admin_tools')]
class TextfieldCharacterCounterWidgetTest extends UnitTestCase {

  /**
   * Creates a widget instance with the given settings.
   *
   * @param array $settings
   *   The widget settings.
   *
   * @return \Drupal\hdbt_admin_tools\Plugin\Field\FieldWidget\TextfieldCharacterCounterWidget
   *   The widget instance.
   */
  private function createWidget(array $settings = []): TextfieldCharacterCounterWidget {
    $fieldDefinition = $this->prophesize(FieldDefinitionInterface::class);
    $fieldDefinition->getSetting('max_length')->willReturn(255);

    $widget = new TextfieldCharacterCounterWidget('textfield_character_counter', [], $fieldDefinition->reveal(), $settings, []);
    $widget->setStringTranslation($this->getStringTranslationStub());
    return $widget;
  }

  /**
   * Builds a field item list with a single saved value.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface
   *   The field item list double.
   */
  private function makeItems(string $value = ''): FieldItemListInterface {
    $item = new \stdClass();
    $item->value = $value;

    $items = $this->prophesize(FieldItemListInterface::class);
    $items->offsetExists(Argument::any())->willReturn(TRUE);
    $items->offsetGet(Argument::any())->willReturn($item);
    return $items->reveal();
  }

  /**
   * Tests that the widget overrides the trait default settings.
   */
  public function testDefaultSettings(): void {
    $defaults = TextfieldCharacterCounterWidget::defaultSettings();
    $this->assertSame(0, $defaults['counter_step']);
    $this->assertSame(55, $defaults['counter_total']);
    $this->assertSame(0, $defaults['counter_max']);
    $this->assertArrayHasKey('size', $defaults);
    $this->assertArrayHasKey('placeholder', $defaults);
  }

  /**
   * Tests that the settings form exposes the counter fields.
   */
  public function testSettingsForm(): void {
    $widget = $this->createWidget();
    $element = $widget->settingsForm([], new FormState());

    $this->assertSame('number', $element['counter_step']['#type']);
    $this->assertSame('number', $element['counter_total']['#type']);
    $this->assertSame('number', $element['counter_max']['#type']);
    $this->assertSame(0, $element['counter_step']['#default_value']);
    $this->assertSame(55, $element['counter_total']['#default_value']);
    $this->assertSame(0, $element['counter_max']['#default_value']);
    $this->assertTrue($element['counter_max']['#required']);
    $this->assertSame(0, $element['counter_max']['#min']);
    $this->assertArrayHasKey('size', $element);
  }

  /**
   * Tests that the settings summary lists the counter values.
   */
  public function testSettingsSummary(): void {
    $widget = $this->createWidget(['counter_step' => 10, 'counter_total' => 20, 'counter_max' => 30]);
    $summary = array_map('strval', $widget->settingsSummary());

    $this->assertContains('Suggestion text character count: 10', $summary);
    $this->assertContains('Warning text character count: 20', $summary);
    $this->assertContains('Maximum text character count: 30', $summary);
  }

  /**
   * Tests that the form element carries the counter properties.
   */
  public function testFormElement(): void {
    $widget = $this->createWidget(['counter_step' => 10, 'counter_total' => 20, 'counter_max' => 30]);

    $form = [];
    $element = $widget->formElement($this->makeItems('saved'), 0, [], $form, new FormState());

    $this->assertTrue($element['value']['#character_counter']);
    $this->assertSame(10, $element['value']['#counter_step']);
    $this->assertSame(20, $element['value']['#counter_total']);
    $this->assertSame(30, $element['value']['#counter_max']);
    $this->assertSame('textfield', $element['value']['#type']);
    $this->assertSame('saved', $element['value']['#default_value']);
  }

}
