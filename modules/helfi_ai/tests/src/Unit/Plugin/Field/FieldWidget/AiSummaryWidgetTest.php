<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ai\Unit\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormState;
use Drupal\helfi_ai\Plugin\Field\FieldWidget\AiSummaryWidget;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Prophecy\Argument;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tests the AI summary field widget.
 */
#[Group('helfi_ai')]
class AiSummaryWidgetTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    parent::tearDown();
    // Reset the container so it doesn't leak into other tests.
    $container = $this->createMock(ContainerInterface::class);
    \Drupal::setContainer($container);
  }

  /**
   * Creates a widget instance for the given field name.
   */
  private function createWidget(string $fieldName = 'ai_summary'): AiSummaryWidget {
    $fieldDef = $this->prophesize(FieldDefinitionInterface::class);
    $fieldDef->getName()->willReturn($fieldName);
    $widget = new AiSummaryWidget('ai_summary', [], $fieldDef->reveal(), [], []);
    $widget->setStringTranslation($this->getStringTranslationStub());
    return $widget;
  }

  /**
   * Builds a FieldItemListInterface prophecy with a single item value.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface>
   *   The field item list prophecy double.
   */
  private function makeItems(string $savedValue, string $fieldName = 'ai_summary'): FieldItemListInterface {
    $fieldDef = $this->prophesize(FieldDefinitionInterface::class);
    $fieldDef->getName()->willReturn($fieldName);

    $item = new \stdClass();
    $item->value = $savedValue;

    $items = $this->prophesize(FieldItemListInterface::class);
    $items->getFieldDefinition()->willReturn($fieldDef->reveal());
    $items->offsetExists(Argument::any())->willReturn(TRUE);
    $items->offsetGet(Argument::any())->willReturn($item);
    return $items->reveal();
  }

  /**
   * Test that the widget applies to the ai_summary field.
   */
  public function testIsApplicableReturnsTrueForAiSummaryField(): void {
    $fieldDef = $this->prophesize(FieldDefinitionInterface::class);
    $fieldDef->getName()->willReturn('ai_summary');
    $this->assertTrue(AiSummaryWidget::isApplicable($fieldDef->reveal()));
  }

  /**
   * Test that the widget does not apply to other fields.
   */
  public function testIsApplicableReturnsFalseForOtherFields(): void {
    $fieldDef = $this->prophesize(FieldDefinitionInterface::class);
    $fieldDef->getName()->willReturn('field_body');
    $this->assertFalse(AiSummaryWidget::isApplicable($fieldDef->reveal()));
  }

  /**
   * Test that an empty field renders only the generate button.
   */
  public function testFormElementEmptyValueBuildsGenerateButton(): void {
    $widget = $this->createWidget();

    $form = [];
    $formState = new FormState();
    $result = $widget->formElement($this->makeItems(''), 0, [], $form, $formState);

    $wrapper = $result['ajax_wrapper'];
    $this->assertSame('ai-summary-ai-summary-0', $wrapper['#attributes']['id']);
    // Empty field: the editor container is hidden, only the button shows.
    $this->assertArrayHasKey('summary', $wrapper);
    $this->assertContains('hidden', $wrapper['summary']['#attributes']['class']);
    $this->assertArrayHasKey('field_label', $wrapper);
    $this->assertArrayHasKey('value', $wrapper['summary']);
    $this->assertSame('text_format', $wrapper['summary']['value']['#type']);
    $this->assertSame('', $wrapper['summary']['value']['#default_value']);
    $this->assertSame('minimal', $wrapper['summary']['value']['#format']);
    $this->assertArrayHasKey('generate', $wrapper);
    $this->assertSame('ai_summary_generate_ai_summary_0', $wrapper['generate']['#name']);
    $this->assertSame('Generate AI summary', (string) $wrapper['generate']['#value']);
    $this->assertContains('helfi_ai/ai_summary_confirm', $wrapper['generate']['#attached']['library']);
    $this->assertArrayNotHasKey('data-helfi-ai-summary-confirm', $wrapper['generate']['#attributes'] ?? []);
    $this->assertArrayHasKey('description', $wrapper);
    $this->assertArrayNotHasKey('error', $wrapper);
  }

  /**
   * Test that a saved value shows the editor and regenerate button.
   */
  public function testFormElementWithSavedValueShowsRegenerateAndDefault(): void {
    $widget = $this->createWidget();

    $form = [];
    $formState = new FormState();
    $result = $widget->formElement($this->makeItems('<ul><li>Saved</li></ul>'), 0, [], $form, $formState);

    $wrapper = $result['ajax_wrapper'];
    $this->assertNotContains('hidden', $wrapper['summary']['#attributes']['class']);
    $this->assertSame('<ul><li>Saved</li></ul>', $wrapper['summary']['value']['#default_value']);
    $this->assertSame('Regenerate AI summary', (string) $wrapper['generate']['#value']);
    $this->assertArrayHasKey('data-helfi-ai-summary-confirm', $wrapper['generate']['#attributes']);
    $this->assertContains('helfi_ai/ai_summary_confirm', $wrapper['generate']['#attached']['library']);
  }

  /**
   * Test that the wrapper id includes the field delta.
   */
  public function testFormElementWrapperIdIncludesDelta(): void {
    $widget = $this->createWidget();

    $form = [];
    $formState = new FormState();
    $result = $widget->formElement($this->makeItems(''), 2, [], $form, $formState);

    $this->assertSame('ai-summary-ai-summary-2', $result['ajax_wrapper']['#attributes']['id']);
  }

  /**
   * Test that the generate button is a plain ajax button.
   */
  public function testFormElementButtonIsPlainAjaxButtonWithoutSubmit(): void {
    $widget = $this->createWidget();

    $form = [];
    $formState = new FormState();
    $result = $widget->formElement($this->makeItems(''), 0, [], $form, $formState);

    $button = $result['ajax_wrapper']['generate'];
    $this->assertSame('button', $button['#type']);
    $this->assertArrayNotHasKey('#submit', $button);
    $this->assertArrayNotHasKey('#executes_submit_callback', $button);
    $this->assertArrayNotHasKey('#limit_validation_errors', $button);
    $this->assertSame([AiSummaryWidget::class, 'ajaxCallback'], $button['#ajax']['callback']);
    $this->assertSame('ai-summary-ai-summary-0', $button['#ajax']['wrapper']);
    $this->assertSame('click', $button['#ajax']['event']);
  }

  /**
   * Test that the edited value is read from the nested form path.
   */
  public function testExtractFormValuesSetsValueFromNestedPath(): void {
    $widget = $this->createWidget();

    $formState = new FormState();
    $formState->setValue(['ai_summary', 0, 'ajax_wrapper', 'summary', 'value'], [
      'value' => '<ul><li>Edited</li></ul>',
      'format' => 'minimal',
    ]);

    $items = $this->prophesize(FieldItemListInterface::class);
    $items->setValue([['value' => '<ul><li>Edited</li></ul>', 'format' => 'minimal']])->shouldBeCalled();

    $widget->extractFormValues($items->reveal(), [], $formState);
  }

  /**
   * Test that nothing is set when no value is submitted.
   */
  public function testExtractFormValuesSkipsWhenNoValue(): void {
    $widget = $this->createWidget();

    $formState = new FormState();

    $items = $this->prophesize(FieldItemListInterface::class);
    $items->setValue(Argument::any())->shouldNotBeCalled();

    $widget->extractFormValues($items->reveal(), [], $formState);
  }

}
