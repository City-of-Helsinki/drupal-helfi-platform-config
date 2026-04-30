<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ai_summary\Unit\Plugin\Field\FieldWidget;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Entity\ContentEntityFormInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\helfi_ai_summary\Plugin\Field\FieldWidget\AiSummaryWidget;
use Drupal\helfi_ai_summary\Service\AiSummaryGenerator;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @coversDefaultClass \Drupal\helfi_ai_summary\Plugin\Field\FieldWidget\AiSummaryWidget
 * @group helfi_ai_summary
 */
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
  private function createWidget(string $fieldName = 'field_ai_summary'): AiSummaryWidget {
    $fieldDef = $this->prophesize(FieldDefinitionInterface::class);
    $fieldDef->getName()->willReturn($fieldName);
    return new AiSummaryWidget('ai_summary', [], $fieldDef->reveal(), [], []);
  }

  /**
   * Builds a FormState wired with a triggering button and entity form object.
   */
  private function makeFormState(string $action, string $fieldName, int $delta): FormState {
    $language = $this->prophesize(LanguageInterface::class);
    $language->getId()->willReturn('fi');

    $entity = $this->prophesize(ContentEntityInterface::class);
    $entity->language()->willReturn($language->reveal());

    $formObject = $this->prophesize(ContentEntityFormInterface::class);
    $formObject->getEntity()->willReturn($entity->reveal());

    $formState = new FormState();
    $formState->setTriggeringElement([
      '#name' => 'ai_summary_' . $action . '_' . $fieldName . '_' . $delta,
    ]);
    $formState->setFormObject($formObject->reveal());
    return $formState;
  }

  /**
   * @covers ::isApplicable
   */
  public function testIsApplicableReturnsTrueForAiSummaryField(): void {
    $fieldDef = $this->prophesize(FieldDefinitionInterface::class);
    $fieldDef->getName()->willReturn('field_ai_summary');
    $this->assertTrue(AiSummaryWidget::isApplicable($fieldDef->reveal()));
  }

  /**
   * @covers ::isApplicable
   */
  public function testIsApplicableReturnsFalseForOtherFields(): void {
    $fieldDef = $this->prophesize(FieldDefinitionInterface::class);
    $fieldDef->getName()->willReturn('field_body');
    $this->assertFalse(AiSummaryWidget::isApplicable($fieldDef->reveal()));
  }

  /**
   * @covers ::extractFormValues
   */
  public function testExtractFormValuesUsesStateWhenPresent(): void {
    $widget = $this->createWidget();

    $formState = new FormState();
    $formState->set('ai_summary_state_field_ai_summary_0', [
      'mode' => 'accepted',
      'value' => 'Summary content here',
      'original' => '',
      'error' => '',
    ]);

    $items = $this->prophesize(FieldItemListInterface::class);
    $items->setValue([['value' => 'Summary content here', 'format' => 'minimal']])->shouldBeCalled();

    $widget->extractFormValues($items->reveal(), [], $formState);
  }

  /**
   * @covers ::extractFormValues
   */
  public function testExtractFormValuesSkipsWhenNoState(): void {
    $widget = $this->createWidget();

    $formState = new FormState();

    $items = $this->prophesize(FieldItemListInterface::class);
    $items->setValue(Argument::any())->shouldNotBeCalled();

    $widget->extractFormValues($items->reveal(), [], $formState);
  }

  /**
   * @covers ::buttonSubmit
   */
  public function testButtonSubmitAccept(): void {
    $formState = $this->makeFormState('accept', 'field_ai_summary', 0);
    $formState->set('ai_summary_state_field_ai_summary_0', [
      'mode' => 'draft',
      'value' => 'Draft text',
      'original' => '',
      'error' => '',
    ]);
    $formState->setUserInput([
      'field_ai_summary' => [
        0 => ['ajax_wrapper' => ['value' => ['value' => 'User edited text']]],
      ],
    ]);

    $form = [];
    AiSummaryWidget::buttonSubmit($form, $formState);

    $state = $formState->get('ai_summary_state_field_ai_summary_0');
    $this->assertSame('accepted', $state['mode']);
    $this->assertSame('User edited text', $state['value']);
  }

  /**
   * @covers ::buttonSubmit
   */
  public function testButtonSubmitRejectWithOriginal(): void {
    $formState = $this->makeFormState('reject', 'field_ai_summary', 0);
    $formState->setUserInput([]);
    $formState->set('ai_summary_state_field_ai_summary_0', [
      'mode' => 'draft',
      'value' => 'Draft text',
      'original' => 'Original saved text',
      'error' => '',
    ]);

    $form = [];
    AiSummaryWidget::buttonSubmit($form, $formState);

    $state = $formState->get('ai_summary_state_field_ai_summary_0');
    $this->assertSame('accepted', $state['mode']);
    $this->assertSame('Original saved text', $state['value']);
  }

  /**
   * @covers ::buttonSubmit
   */
  public function testButtonSubmitRejectWithoutOriginal(): void {
    $formState = $this->makeFormState('reject', 'field_ai_summary', 0);
    $formState->setUserInput([]);
    $formState->set('ai_summary_state_field_ai_summary_0', [
      'mode' => 'draft',
      'value' => 'Draft text',
      'original' => '',
      'error' => '',
    ]);

    $form = [];
    AiSummaryWidget::buttonSubmit($form, $formState);

    $state = $formState->get('ai_summary_state_field_ai_summary_0');
    $this->assertSame('initial', $state['mode']);
    $this->assertSame('', $state['value']);
  }

  /**
   * @covers ::buttonSubmit
   */
  public function testButtonSubmitGenerateSuccess(): void {
    $mockGenerator = $this->prophesize(AiSummaryGenerator::class);
    $mockGenerator->generate(Argument::any(), 'fi')->willReturn('<ul><li>Summary point</li></ul>');

    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')->willReturnCallback(
      function (string $id) use ($mockGenerator): object {
        if ($id === AiSummaryGenerator::class) {
          return $mockGenerator->reveal();
        }
        throw new \RuntimeException('Unexpected service: ' . $id);
      }
    );
    \Drupal::setContainer($container);

    $formState = $this->makeFormState('generate', 'field_ai_summary', 0);
    $formState->setUserInput([]);
    $formState->set('ai_summary_state_field_ai_summary_0', [
      'mode' => 'initial',
      'value' => '',
      'original' => '',
      'error' => '',
    ]);

    $form = [];
    AiSummaryWidget::buttonSubmit($form, $formState);

    $state = $formState->get('ai_summary_state_field_ai_summary_0');
    $this->assertSame('draft', $state['mode']);
    $this->assertSame('<ul><li>Summary point</li></ul>', $state['value']);
  }

  /**
   * @covers ::buttonSubmit
   */
  public function testButtonSubmitGenerateNullSetsError(): void {
    $mockGenerator = $this->prophesize(AiSummaryGenerator::class);
    $mockGenerator->generate(Argument::any(), 'fi')->willReturn(NULL);

    $stringTranslation = $this->getStringTranslationStub();

    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')->willReturnCallback(
      function (string $id) use ($mockGenerator, $stringTranslation): object {
        if ($id === AiSummaryGenerator::class) {
          return $mockGenerator->reveal();
        }
        if ($id === 'string_translation') {
          return $stringTranslation;
        }
        throw new \RuntimeException('Unexpected service: ' . $id);
      }
    );
    \Drupal::setContainer($container);

    $formState = $this->makeFormState('generate', 'field_ai_summary', 0);
    $formState->set('ai_summary_state_field_ai_summary_0', [
      'mode' => 'initial',
      'value' => '',
      'original' => '',
      'error' => '',
    ]);

    $form = [];
    AiSummaryWidget::buttonSubmit($form, $formState);

    $state = $formState->get('ai_summary_state_field_ai_summary_0');
    $this->assertSame('initial', $state['mode']);
    $this->assertNotEmpty($state['error']);
  }

  /**
   * Builds a FieldItemListInterface prophecy with a single item value.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface>
   *   The field item list prophecy double.
   */
  private function makeItems(string $savedValue, string $fieldName = 'field_ai_summary'): FieldItemListInterface {
    $fieldDef = $this->prophesize(FieldDefinitionInterface::class);
    $fieldDef->getName()->willReturn($fieldName);

    $item = new \stdClass();
    $item->value = $savedValue;

    $items = $this->prophesize(FieldItemListInterface::class);
    $items->getFieldDefinition()->willReturn($fieldDef->reveal());
    $items->offsetExists(0)->willReturn(TRUE);
    $items->offsetGet(0)->willReturn($item);
    return $items->reveal();
  }

  /**
   * @covers ::formElement
   */
  public function testFormElementInitialModeBuildsGenerateButton(): void {
    $widget = $this->createWidget();
    $widget->setStringTranslation($this->getStringTranslationStub());

    $form = [];
    $formState = new FormState();
    $result = $widget->formElement($this->makeItems(''), 0, [], $form, $formState);

    $wrapper = $result['ajax_wrapper'];
    $this->assertSame('ai-summary-field-ai-summary-0', $wrapper['#attributes']['id']);
    $this->assertArrayHasKey('generate', $wrapper);
    $this->assertArrayNotHasKey('value', $wrapper);
  }

  /**
   * @covers ::formElement
   */
  public function testFormElementAcceptedModeBuildsTextFormatAndRegenerateButton(): void {
    $widget = $this->createWidget();
    $widget->setStringTranslation($this->getStringTranslationStub());

    $form = [];
    $formState = new FormState();
    $result = $widget->formElement($this->makeItems('<ul><li>Saved</li></ul>'), 0, [], $form, $formState);

    $wrapper = $result['ajax_wrapper'];
    $this->assertArrayHasKey('value', $wrapper);
    $this->assertArrayHasKey('regenerate', $wrapper);
    $this->assertArrayNotHasKey('generate', $wrapper);
  }

  /**
   * @covers ::formElement
   */
  public function testFormElementDraftModeBuildsAcceptAndRejectButtons(): void {
    $widget = $this->createWidget();
    $widget->setStringTranslation($this->getStringTranslationStub());

    $form = [];
    $formState = new FormState();
    $formState->set('ai_summary_state_field_ai_summary_0', [
      'mode' => 'draft',
      'value' => '<ul><li>Draft</li></ul>',
      'original' => '',
      'error' => '',
    ]);

    $result = $widget->formElement($this->makeItems(''), 0, [], $form, $formState);

    $wrapper = $result['ajax_wrapper'];
    $this->assertArrayHasKey('accept', $wrapper);
    $this->assertArrayHasKey('reject', $wrapper);
    $this->assertArrayHasKey('value', $wrapper);
  }

  /**
   * @covers ::formElement
   */
  public function testFormElementShowsErrorWhenSet(): void {
    $widget = $this->createWidget();
    $widget->setStringTranslation($this->getStringTranslationStub());

    $form = [];
    $formState = new FormState();
    $formState->set('ai_summary_state_field_ai_summary_0', [
      'mode' => 'initial',
      'value' => '',
      'original' => '',
      'error' => 'Something went wrong.',
    ]);

    $result = $widget->formElement($this->makeItems(''), 0, [], $form, $formState);

    $this->assertArrayHasKey('error', $result['ajax_wrapper']);
    $this->assertSame('Something went wrong.', $result['ajax_wrapper']['error']['#value']);
  }

  /**
   * @covers ::buttonSubmit
   */
  public function testButtonSubmitIgnoresInvalidTriggerName(): void {
    $formState = new FormState();
    $formState->setTriggeringElement(['#name' => 'some_unrelated_button']);

    $form = [];
    AiSummaryWidget::buttonSubmit($form, $formState);

    // No state was written; rebuild was not set.
    $this->assertFalse($formState->isRebuilding());
  }

  /**
   * @covers ::buttonSubmit
   */
  public function testButtonSubmitIgnoresNonEntityFormObject(): void {
    $formObject = $this->prophesize(FormInterface::class);

    $formState = new FormState();
    $formState->setTriggeringElement(['#name' => 'ai_summary_accept_field_ai_summary_0']);
    $formState->setFormObject($formObject->reveal());

    $form = [];
    AiSummaryWidget::buttonSubmit($form, $formState);

    $this->assertFalse($formState->isRebuilding());
  }

  /**
   * @covers ::formElement
   */
  public function testFormElementAcceptedModeFromStateShowsRegenerateButton(): void {
    $widget = $this->createWidget();
    $widget->setStringTranslation($this->getStringTranslationStub());

    $form = [];
    $formState = new FormState();
    $formState->set('ai_summary_state_field_ai_summary_0', [
      'mode' => 'accepted',
      'value' => '<ul><li>Accepted summary</li></ul>',
      'original' => '<ul><li>Accepted summary</li></ul>',
      'error' => '',
    ]);

    $result = $widget->formElement($this->makeItems(''), 0, [], $form, $formState);

    $wrapper = $result['ajax_wrapper'];
    $this->assertArrayHasKey('regenerate', $wrapper);
    $this->assertArrayHasKey('value', $wrapper);
    $this->assertArrayNotHasKey('generate', $wrapper);
    $this->assertArrayNotHasKey('accept', $wrapper);
  }

  /**
   * @covers ::formElement
   */
  public function testFormElementWrapperIdIncludesDelta(): void {
    $widget = $this->createWidget();
    $widget->setStringTranslation($this->getStringTranslationStub());

    $form = [];
    $formState = new FormState();
    $result = $widget->formElement($this->makeItems('', 'field_ai_summary'), 0, [], $form, $formState);

    $this->assertSame('ai-summary-field-ai-summary-0', $result['ajax_wrapper']['#attributes']['id']);
  }

  /**
   * @covers ::formElement
   */
  public function testFormElementFieldLabelAlwaysPresent(): void {
    $widget = $this->createWidget();
    $widget->setStringTranslation($this->getStringTranslationStub());

    $form = [];
    $formState = new FormState();
    $result = $widget->formElement($this->makeItems(''), 0, [], $form, $formState);

    $this->assertArrayHasKey('field_label', $result['ajax_wrapper']);
    $this->assertSame('label', $result['ajax_wrapper']['field_label']['#tag']);
    $this->assertSame(-200, $result['ajax_wrapper']['field_label']['#weight']);
  }

  /**
   * @covers ::formElement
   */
  public function testFormElementModeDescriptionPresent(): void {
    $widget = $this->createWidget();
    $widget->setStringTranslation($this->getStringTranslationStub());

    $form = [];
    $formState = new FormState();
    $result = $widget->formElement($this->makeItems(''), 0, [], $form, $formState);

    $this->assertArrayHasKey('mode_description', $result['ajax_wrapper']);
    $this->assertSame('div', $result['ajax_wrapper']['mode_description']['#tag']);
    $this->assertSame(100, $result['ajax_wrapper']['mode_description']['#weight']);
  }

  /**
   * @covers ::buttonSubmit
   */
  public function testButtonSubmitGenerateWritesValueToUserInput(): void {
    $mockGenerator = $this->prophesize(AiSummaryGenerator::class);
    $mockGenerator->generate(Argument::any(), 'fi')->willReturn('<ul><li>Generated</li></ul>');

    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')->willReturnCallback(
      function (string $id) use ($mockGenerator): object {
        if ($id === AiSummaryGenerator::class) {
          return $mockGenerator->reveal();
        }
        throw new \RuntimeException('Unexpected service: ' . $id);
      }
    );
    \Drupal::setContainer($container);

    $formState = $this->makeFormState('generate', 'field_ai_summary', 0);
    $formState->setUserInput([]);
    $formState->set('ai_summary_state_field_ai_summary_0', [
      'mode' => 'initial',
      'value' => '',
      'original' => '',
      'error' => '',
    ]);

    $form = [];
    AiSummaryWidget::buttonSubmit($form, $formState);

    $input = $formState->getUserInput();
    $this->assertSame(
      '<ul><li>Generated</li></ul>',
      $input['field_ai_summary'][0]['ajax_wrapper']['value']['value'],
    );
    $this->assertSame('minimal', $input['field_ai_summary'][0]['ajax_wrapper']['value']['format']);
  }

  /**
   * @covers ::buttonSubmit
   */
  public function testButtonSubmitRejectWritesValueToUserInput(): void {
    $formState = $this->makeFormState('reject', 'field_ai_summary', 0);
    $formState->setUserInput([]);
    $formState->set('ai_summary_state_field_ai_summary_0', [
      'mode' => 'draft',
      'value' => 'Draft text',
      'original' => 'Original',
      'error' => '',
    ]);

    $form = [];
    AiSummaryWidget::buttonSubmit($form, $formState);

    $input = $formState->getUserInput();
    $this->assertSame('Original', $input['field_ai_summary'][0]['ajax_wrapper']['value']['value']);
    $this->assertSame('minimal', $input['field_ai_summary'][0]['ajax_wrapper']['value']['format']);
  }

  /**
   * @covers ::buttonSubmit
   */
  public function testButtonSubmitAcceptReadsEditedValueFromUserInput(): void {
    $formState = $this->makeFormState('accept', 'field_ai_summary', 0);
    $formState->set('ai_summary_state_field_ai_summary_0', [
      'mode' => 'draft',
      'value' => 'Old draft',
      'original' => '',
      'error' => '',
    ]);
    $formState->setUserInput([
      'field_ai_summary' => [
        0 => ['ajax_wrapper' => ['value' => ['value' => 'Edited by user']]],
      ],
    ]);

    $form = [];
    AiSummaryWidget::buttonSubmit($form, $formState);

    $state = $formState->get('ai_summary_state_field_ai_summary_0');
    $this->assertSame('accepted', $state['mode']);
    $this->assertSame('Edited by user', $state['value']);
    $this->assertTrue($formState->isRebuilding());
  }

  /**
   * @covers ::buttonSubmit
   */
  public function testButtonSubmitSetsRebuildOnSuccess(): void {
    $formState = $this->makeFormState('reject', 'field_ai_summary', 0);
    $formState->setUserInput([]);
    $formState->set('ai_summary_state_field_ai_summary_0', [
      'mode' => 'draft',
      'value' => 'Draft',
      'original' => '',
      'error' => '',
    ]);

    $form = [];
    AiSummaryWidget::buttonSubmit($form, $formState);

    $this->assertTrue($formState->isRebuilding());
  }

  /**
   * @covers ::ajaxCallback
   */
  public function testAjaxCallbackReturnsReplaceResponse(): void {
    $renderer = $this->createMock(RendererInterface::class);
    $renderer->method('renderRoot')->willReturn('<div>rendered</div>');

    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')->willReturnCallback(
      function (string $id) use ($renderer): object {
        if ($id === 'renderer') {
          return $renderer;
        }
        throw new \RuntimeException('Unexpected service: ' . $id);
      }
    );
    \Drupal::setContainer($container);

    $wrapperId = 'ai-summary-field-ai-summary-0';
    $form = [
      'field_ai_summary' => [
        0 => [
          'ajax_wrapper' => [
            '#type' => 'container',
            '#attributes' => ['id' => $wrapperId],
            '#attached' => [],
          ],
        ],
      ],
    ];

    $formState = new FormState();
    $formState->setTriggeringElement([
      '#array_parents' => ['field_ai_summary', 0, 'ajax_wrapper', 'generate'],
    ]);

    $response = AiSummaryWidget::ajaxCallback($form, $formState);
    $this->assertInstanceOf(AjaxResponse::class, $response);
  }

}
