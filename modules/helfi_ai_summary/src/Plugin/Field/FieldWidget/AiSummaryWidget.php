<?php

declare(strict_types=1);

namespace Drupal\helfi_ai_summary\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\ContentEntityFormInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_ai_summary\Service\AiSummaryGenerator;

/**
 * Widget for the AI summary field with a three-state AJAX flow.
 *
 * Modes:
 *   - initial: Generate button only.
 *   - draft: editable WYSIWYG textarea, Accept and Reject buttons.
 *   - accepted: editable WYSIWYG textarea, Regenerate button.
 *
 * The current mode and value live in the form state. AJAX button handlers
 * mutate that state and write back into raw user input so a form rebuild
 * shows the new value. extractFormValues() reads from the form state when
 * the parent node form is saved, so the persisted value matches what the
 * user accepted, rejected, or regenerated.
 */
#[FieldWidget(
  id: 'ai_summary',
  label: new TranslatableMarkup('AI Summary'),
  field_types: ['text_long'],
)]
final class AiSummaryWidget extends WidgetBase {

  /**
   * Text format used for AI summary content. Allows <ul>, <li>, links.
   */
  private const TEXT_FORMAT = 'minimal';

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    return $field_definition->getName() === 'field_ai_summary';
  }

  /**
   * Reads accepted summary from form state and sets it on the field.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface> $items
   *   The field values.
   * @param array<string, mixed> $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state): void {
    // Skip the parent which reads from raw input; we read from form state
    // because the accepted value is authoritative there.
    $field_name = $this->fieldDefinition->getName();
    $state = self::readState($form_state, $field_name, 0);
    if ($state !== NULL) {
      $items->setValue([
        [
          'value' => $state['value'],
          'format' => self::TEXT_FORMAT,
        ],
      ]);
    }
  }

  /**
   * Builds the widget form element for a single field delta.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface> $items
   *   The field values.
   * @param int $delta
   *   The current delta.
   * @param array<string, mixed> $element
   *   The base element.
   * @param array<string, mixed> $form
   *   The parent form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return array<string, mixed>
   *   The rendered form element.
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $field_name = $items->getFieldDefinition()->getName();
    $wrapper_id = 'ai-summary-' . str_replace('_', '-', $field_name) . '-' . $delta;

    $saved_value = $items[$delta]->value ?? '';
    $state = self::initState($form_state, $field_name, $delta, $saved_value);
    $mode = $state['mode'];

    // Label rendered as a sibling above the AJAX container so AJAX
    // replacements targeting $wrapper_id never remove or duplicate it.
    $element['field_label'] = [
      '#type' => 'html_tag',
      '#tag' => 'label',
      '#value' => $this->t('AI summary', options: ['context' => 'helfi_ai_summary']),
      '#attributes' => ['class' => ['form-item__label']],
      '#weight' => -50,
    ];

    // All dynamic content lives inside this container. It is the sole AJAX
    // replacement target, so the label above is never affected.
    $element['ajax_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => $wrapper_id],
      '#weight' => 0,
    ];
    $wrapper = &$element['ajax_wrapper'];

    if (!empty($state['error'])) {
      $wrapper['error'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $state['error'],
        '#attributes' => ['class' => ['messages', 'messages--error']],
        '#weight' => -20,
      ];
      self::updateState($form_state, $field_name, $delta, ['error' => '']);
    }

    if ($mode !== 'initial') {
      $wrapper['value'] = [
        '#type' => 'text_format',
        '#title' => $this->t('AI summary', options: ['context' => 'helfi_ai_summary']),
        '#title_display' => 'invisible',
        '#default_value' => $state['value'],
        '#format' => self::TEXT_FORMAT,
        '#allowed_formats' => [self::TEXT_FORMAT],
        '#rows' => 6,
      ];
    }

    $wrapper += $this->buildButtons($mode, $field_name, $delta, $wrapper_id);

    $wrapper['mode_description'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $this->modeDescription($mode),
      '#attributes' => ['class' => ['description', 'form-item__description']],
      '#weight' => 100,
    ];

    return $element;
  }

  /**
   * Returns the helper text shown below the buttons for the given mode.
   */
  private function modeDescription(string $mode): string {
    $ctx = ['context' => 'helfi_ai_summary'];
    return match ($mode) {
      'draft' => (string) $this->t('You can edit the text before accepting.', options: $ctx),
      'accepted' => (string) $this->t('The summary is saved with the page. You can create a new suggestion at any time.', options: $ctx),
      default => (string) $this->t('AI generates a 4–6 bullet point summary of the page. You can edit the summary before accepting.', options: $ctx),
    };
  }

  /**
   * Builds the action buttons rendered for the given mode.
   *
   * @param string $mode
   *   Current widget mode: initial, draft, or accepted.
   * @param string $field_name
   *   Machine name of the field.
   * @param int $delta
   *   Field delta.
   * @param string $wrapper_id
   *   HTML id of the AJAX wrapper element.
   *
   * @return array<string, mixed>
   *   Render array of button elements.
   */
  private function buildButtons(string $mode, string $field_name, int $delta, string $wrapper_id): array {
    $ctx = ['context' => 'helfi_ai_summary'];
    $buttons = [];
    if ($mode === 'initial') {
      $buttons['generate'] = $this->button('generate', $this->t('Generate AI summary', options: $ctx), $field_name, $delta, $wrapper_id, TRUE);
    }
    elseif ($mode === 'draft') {
      $buttons['accept'] = $this->button('accept', $this->t('Accept', options: $ctx), $field_name, $delta, $wrapper_id);
      $buttons['accept']['#button_type'] = 'primary';
      $buttons['reject'] = $this->button('reject', $this->t('Reject', options: $ctx), $field_name, $delta, $wrapper_id);
    }
    elseif ($mode === 'accepted') {
      $buttons['regenerate'] = $this->button('generate', $this->t('Regenerate AI summary', options: $ctx), $field_name, $delta, $wrapper_id, TRUE);
      $buttons['regenerate']['#attributes']['class'][] = 'button--link';
    }
    return $buttons;
  }

  /**
   * Builds a single AJAX button render array.
   *
   * @param string $action
   *   Button action: generate, accept, or reject.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   Button label.
   * @param string $field_name
   *   Machine name of the field.
   * @param int $delta
   *   Field delta.
   * @param string $wrapper_id
   *   HTML id of the AJAX wrapper element.
   * @param bool $with_progress
   *   Whether to show an AJAX throbber while waiting.
   *
   * @return array<string, mixed>
   *   Render array for the button.
   */
  private function button(string $action, TranslatableMarkup $label, string $field_name, int $delta, string $wrapper_id, bool $with_progress = FALSE): array {
    $button = [
      '#type' => 'button',
      '#value' => $label,
      '#name' => 'ai_summary_' . $action . '_' . $field_name . '_' . $delta,
      '#executes_submit_callback' => TRUE,
      '#limit_validation_errors' => [],
      '#submit' => [[static::class, 'buttonSubmit']],
      '#ajax' => [
        'callback' => [static::class, 'ajaxCallback'],
        'wrapper' => $wrapper_id,
      ],
    ];
    if ($with_progress) {
      $button['#ajax']['progress'] = [
        'type' => 'throbber',
        'message' => $this->t('AI is creating a summary…', options: ['context' => 'helfi_ai_summary']),
      ];
    }
    return $button;
  }

  /**
   * Submit handler shared by all widget action buttons.
   *
   * Updates the widget state and the raw user input before the form is
   * rebuilt. The AJAX callback then returns the rebuilt element.
   *
   * @param array<string, mixed> $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  public static function buttonSubmit(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    if (!preg_match('/^ai_summary_(generate|accept|reject)_(.+)_(\d+)$/', $trigger['#name'] ?? '', $m)) {
      return;
    }
    [, $action, $field_name, $delta] = $m;
    $delta = (int) $delta;

    $form_object = $form_state->getFormObject();
    if (!$form_object instanceof ContentEntityFormInterface) {
      return;
    }
    $entity = $form_object->getEntity();
    if (!$entity instanceof ContentEntityInterface) {
      return;
    }

    switch ($action) {
      case 'generate':
        $generator = \Drupal::service(AiSummaryGenerator::class);
        $summary = $generator->generate($entity, $entity->language()->getId());
        if ($summary !== NULL) {
          self::updateState($form_state, $field_name, $delta, [
            'mode' => 'draft',
            'value' => $summary,
          ]);
          self::writeUserInputValue($form_state, $field_name, $delta, $summary);
        }
        else {
          self::updateState($form_state, $field_name, $delta, [
            'error' => (string) t('Could not generate summary. Ensure the content is saved and the AI provider is configured.', options: ['context' => 'helfi_ai_summary']),
          ]);
        }
        break;

      case 'accept':
        // text_format submits as
        // field[delta][ajax_wrapper][value][value] and [value][format].
        $input = $form_state->getUserInput();
        $edited = NestedArray::getValue($input, [$field_name, $delta, 'ajax_wrapper', 'value', 'value']) ?? '';
        self::updateState($form_state, $field_name, $delta, [
          'mode' => 'accepted',
          'value' => (string) $edited,
        ]);
        break;

      case 'reject':
        $state = self::readState($form_state, $field_name, $delta) ?? [];
        $original = $state['original'] ?? '';
        self::updateState($form_state, $field_name, $delta, [
          'mode' => $original !== '' ? 'accepted' : 'initial',
          'value' => $original,
        ]);
        self::writeUserInputValue($form_state, $field_name, $delta, $original);
        break;
    }

    $form_state->setRebuild(TRUE);
  }

  /**
   * AJAX callback. Returns the rebuilt widget element for the wrapper.
   *
   * @param array<string, mixed> $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Response replacing the widget wrapper with the rebuilt element.
   */
  public static function ajaxCallback(array &$form, FormStateInterface $form_state): AjaxResponse {
    $trigger = $form_state->getTriggeringElement();
    // Buttons are children of ajax_wrapper; slice to -1 to get ajax_wrapper.
    $parents = array_slice($trigger['#array_parents'], 0, -1);
    $wrapper = NestedArray::getValue($form, $parents);
    $wrapper_id = $wrapper['#attributes']['id'] ?? '';

    return (new AjaxResponse())->addCommand(
      new ReplaceCommand('#' . $wrapper_id, $wrapper),
    );
  }

  /**
   * Builds the form-state key for the widget's state bundle.
   */
  private static function stateKey(string $field_name, int $delta): string {
    return 'ai_summary_state_' . $field_name . '_' . $delta;
  }

  /**
   * Returns the stored widget state, or NULL if not initialised.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param string $field_name
   *   Machine name of the field.
   * @param int $delta
   *   Field delta.
   *
   * @return array<string, mixed>|null
   *   State array, or NULL if not yet initialised.
   */
  private static function readState(FormStateInterface $form_state, string $field_name, int $delta): ?array {
    return $form_state->get(self::stateKey($field_name, $delta));
  }

  /**
   * Initialises widget state on first render and returns the current state.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param string $field_name
   *   Machine name of the field.
   * @param int $delta
   *   Field delta.
   * @param string $saved_value
   *   The currently stored field value, used to set the initial mode.
   *
   * @return array<string, mixed>
   *   The current state array.
   */
  private static function initState(FormStateInterface $form_state, string $field_name, int $delta, string $saved_value): array {
    $key = self::stateKey($field_name, $delta);
    $state = $form_state->get($key);
    if ($state === NULL) {
      $state = [
        'mode' => $saved_value !== '' ? 'accepted' : 'initial',
        'value' => $saved_value,
        'original' => $saved_value,
        'error' => '',
      ];
      $form_state->set($key, $state);
    }
    return $state;
  }

  /**
   * Merges the given changes into the widget state.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param string $field_name
   *   Machine name of the field.
   * @param int $delta
   *   Field delta.
   * @param array<string, mixed> $changes
   *   Keys to merge into the existing state.
   */
  private static function updateState(FormStateInterface $form_state, string $field_name, int $delta, array $changes): void {
    $key = self::stateKey($field_name, $delta);
    $state = $form_state->get($key) ?? [];
    $form_state->set($key, $changes + $state);
  }

  /**
   * Writes the given value into raw user input.
   *
   * Required so the form rebuild after generate or reject reflects the new
   * value in the text_format element. The element submits as nested keys
   * field[delta][value][value] and field[delta][value][format].
   */
  private static function writeUserInputValue(FormStateInterface $form_state, string $field_name, int $delta, string $value): void {
    $input = $form_state->getUserInput();
    NestedArray::setValue($input, [$field_name, $delta, 'ajax_wrapper', 'value', 'value'], $value);
    NestedArray::setValue($input, [$field_name, $delta, 'ajax_wrapper', 'value', 'format'], self::TEXT_FORMAT);
    $form_state->setUserInput($input);
  }

}
