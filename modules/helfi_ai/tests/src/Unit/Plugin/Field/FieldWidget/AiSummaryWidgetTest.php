<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ai\Unit\Plugin\Field\FieldWidget;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Entity\ContentEntityFormInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\helfi_ai\Plugin\Field\FieldWidget\AiSummaryWidget;
use Drupal\helfi_ai\Service\AiSummaryGenerator;
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

  /**
   * Builds a form + form state wired for the ajax callback.
   *
   * @param string|null $summary
   *   The summary the mocked generator returns.
   * @param array<string, mixed> $captured
   *   Receives the render array passed to the renderer.
   *
   * @return array{0: array<string, mixed>, 1: \Drupal\Core\Form\FormState}
   *   The form structure and form state.
   */
  private function makeAjaxContext(?string $summary, array &$captured): array {
    $language = $this->prophesize(LanguageInterface::class);
    $language->getId()->willReturn('fi');

    $entity = $this->prophesize(ContentEntityInterface::class);
    $entity->isNew()->willReturn(FALSE);
    $entity->language()->willReturn($language->reveal());

    $formObject = $this->prophesize(ContentEntityFormInterface::class);
    $formObject->buildEntity(Argument::cetera())->willReturn($entity->reveal());

    $generator = $this->prophesize(AiSummaryGenerator::class);
    $generator->generate(Argument::any(), 'fi')->willReturn($summary);

    $renderer = $this->createMock(RendererInterface::class);
    $renderer->method('renderRoot')->willReturnCallback(
      function (&$elements) use (&$captured): string {
        $captured = $elements;
        $elements['#attached'] = $elements['#attached'] ?? [];
        return '<div>rendered</div>';
      }
    );

    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')->willReturnCallback(
      function (string $id) use ($generator, $renderer): object {
        return match ($id) {
          AiSummaryGenerator::class => $generator->reveal(),
          'renderer' => $renderer,
          'string_translation' => $this->getStringTranslationStub(),
          default => throw new \RuntimeException('Unexpected service: ' . $id),
        };
      }
    );
    \Drupal::setContainer($container);

    $wrapperId = 'ai-summary-ai-summary-0';
    $form = [
      'ai_summary' => [
        0 => [
          'ajax_wrapper' => [
            '#type' => 'container',
            '#attributes' => ['id' => $wrapperId],
            'summary' => [
              '#type' => 'container',
              '#attributes' => ['class' => ['hidden']],
              'value' => [
                '#type' => 'text_format',
                'value' => ['#type' => 'textarea', '#value' => ''],
                'format' => ['#type' => 'select'],
              ],
            ],
            'generate' => ['#type' => 'button', '#value' => 'Generate AI summary'],
          ],
        ],
      ],
    ];

    $formState = new FormState();
    $formState->setFormObject($formObject->reveal());
    $formState->setTriggeringElement([
      '#array_parents' => ['ai_summary', 0, 'ajax_wrapper', 'generate'],
    ]);

    return [$form, $formState];
  }

  /**
   * Test that a generated summary is injected into the form.
   */
  public function testAjaxCallbackInjectsSummaryOnSuccess(): void {
    $captured = [];
    [$form, $formState] = $this->makeAjaxContext('<ul><li>Generated</li></ul>', $captured);

    $response = AiSummaryWidget::ajaxCallback($form, $formState);

    $this->assertInstanceOf(AjaxResponse::class, $response);
    $commands = $response->getCommands();
    $this->assertCount(1, $commands);
    $this->assertSame('#ai-summary-ai-summary-0', $commands[0]['selector']);
    $this->assertSame('replaceWith', $commands[0]['method']);
    $this->assertSame('<ul><li>Generated</li></ul>', $captured['summary']['value']['value']['#value']);
    $this->assertNotContains('hidden', $captured['summary']['#attributes']['class']);
    $this->assertSame('Regenerate AI summary', (string) $captured['generate']['#value']);
    $this->assertArrayHasKey('data-helfi-ai-summary-confirm', $captured['generate']['#attributes']);
    $this->assertArrayNotHasKey('error', $captured);
  }

  /**
   * Test that an error is shown when generation returns nothing.
   */
  public function testAjaxCallbackShowsErrorWhenGeneratorReturnsNull(): void {
    $captured = [];
    [$form, $formState] = $this->makeAjaxContext(NULL, $captured);

    $response = AiSummaryWidget::ajaxCallback($form, $formState);

    $this->assertInstanceOf(AjaxResponse::class, $response);
    $this->assertArrayHasKey('error', $captured);
    $this->assertSame('', $captured['summary']['value']['value']['#value']);
    $this->assertContains('hidden', $captured['summary']['#attributes']['class']);
  }

}
