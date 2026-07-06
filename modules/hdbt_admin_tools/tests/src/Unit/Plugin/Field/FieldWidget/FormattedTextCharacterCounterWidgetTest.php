<?php

declare(strict_types=1);

namespace Drupal\Tests\hdbt_admin_tools\Unit\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormState;
use Drupal\hdbt_admin_tools\Plugin\Field\FieldWidget\FormattedTextCharacterCounterWidget;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Prophecy\Argument;

/**
 * Tests the formatted text character counter widget.
 */
#[Group('hdbt_admin_tools')]
class FormattedTextCharacterCounterWidgetTest extends UnitTestCase {

  /**
   * Creates a widget instance with the given settings.
   *
   * @param array<string, mixed> $settings
   *   The widget settings.
   *
   * @return \Drupal\hdbt_admin_tools\Plugin\Field\FieldWidget\FormattedTextCharacterCounterWidget
   *   The widget instance.
   */
  private function createWidget(array $settings = []): FormattedTextCharacterCounterWidget {
    $fieldDefinition = $this->prophesize(FieldDefinitionInterface::class);
    $fieldDefinition->getSetting('allowed_formats')->willReturn(NULL);

    $widget = new FormattedTextCharacterCounterWidget('formatted_text_character_counter', [], $fieldDefinition->reveal(), $settings, []);
    $widget->setStringTranslation($this->getStringTranslationStub());
    return $widget;
  }

  /**
   * Builds a field item list with a single saved value.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface>
   *   The field item list double.
   */
  private function makeItems(string $value = '', string $format = 'basic_html'): FieldItemListInterface {
    $item = new \stdClass();
    $item->value = $value;
    $item->format = $format;

    $items = $this->prophesize(FieldItemListInterface::class);
    $items->offsetExists(Argument::any())->willReturn(TRUE);
    $items->offsetGet(Argument::any())->willReturn($item);
    return $items->reveal();
  }

  /**
   * Tests that the widget inherits the trait default settings.
   */
  public function testDefaultSettings(): void {
    $defaults = FormattedTextCharacterCounterWidget::defaultSettings();
    $this->assertSame(160, $defaults['counter_step']);
    $this->assertSame(200, $defaults['counter_total']);
    $this->assertSame(0, $defaults['counter_max']);
  }

  /**
   * Tests that the settings summary lists the counter values.
   */
  public function testSettingsSummary(): void {
    $widget = $this->createWidget();
    $summary = array_map('strval', $widget->settingsSummary());

    $this->assertContains('Suggestion text character count: 160', $summary);
    $this->assertContains('Warning text character count: 200', $summary);
    $this->assertContains('Maximum text character count: 0', $summary);
  }

  /**
   * Tests that the form element carries the counter properties.
   */
  public function testFormElement(): void {
    $widget = $this->createWidget(['counter_step' => 100, 'counter_total' => 300, 'counter_max' => 400]);

    $form = [];
    $element = $widget->formElement($this->makeItems('saved', 'full_html'), 0, [], $form, new FormState());

    $this->assertTrue($element['#character_counter']);
    $this->assertSame(100, $element['#counter_step']);
    $this->assertSame(300, $element['#counter_total']);
    $this->assertSame(400, $element['#counter_max']);
    $this->assertSame('text_format', $element['#type']);
    $this->assertSame('full_html', $element['#format']);
  }

}
