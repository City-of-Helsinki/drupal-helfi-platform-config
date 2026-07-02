<?php

declare(strict_types=1);

namespace Drupal\helfi_ai\Plugin\Field\FieldWidget;

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
use Drupal\helfi_ai\Service\AiSummaryGenerator;

/**
 * Widget for the AI summary field.
 *
 * Renders an editable WYSIWYG field plus a single "Generate" button. Clicking
 * Generate builds the entity from the *current* (unsaved) form values, asks the
 * AI provider for a summary of that live content, and fills the editable field
 * with the result. The editor can then freely edit the text and saves it with
 * the node like any other field.
 *
 * The editor is hidden until a summary exists: an empty field shows only the
 * button. Regenerating a summary that already has content first asks the editor
 * to confirm, so reviewed or hand-edited text is not overwritten by accident.
 *
 * The button is a plain (non-submit) AJAX button on purpose:
 *   - Its AJAX callback runs regardless of validation and sees the full,
 *     un-pruned form values, so
 *     {@see \Drupal\Core\Entity\ContentEntityForm::buildEntity()} reconstructs
 *     the unsaved entity (including unsaved paragraphs) in memory.
 *   - It carries no `#limit_validation_errors`, which would otherwise prune the
 *     submitted values to nothing and hide the editor's unsaved changes.
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
    return $field_definition->getName() === 'ai_summary';
  }

  /**
   * Reads the submitted summary value and sets it on the field.
   *
   * The editable element is nested under the AJAX wrapper's summary container,
   * so its submitted value lives at field[delta][ajax_wrapper][summary][value]
   * rather than the path the parent WidgetBase expects. Read it explicitly
   * here.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface> $items
   *   The field values.
   * @param array<string, mixed> $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state): void {
    $field_name = $this->fieldDefinition->getName();
    $value = $form_state->getValue([$field_name, 0, 'ajax_wrapper', 'summary', 'value']);
    // A text_format element submits as ['value' => ..., 'format' => ...].
    if (is_array($value) && array_key_exists('value', $value)) {
      $items->setValue([
        [
          'value' => (string) $value['value'],
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
    $saved_value = (string) ($items[$delta]->value ?? '');

    // All dynamic content lives inside this container. It is the sole AJAX
    // replacement target.
    $element['ajax_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => $wrapper_id],
      '#weight' => 0,
    ];
    $wrapper = &$element['ajax_wrapper'];

    // Label and editor live inside a container that is hidden until a summary
    // exists: an empty field shows only the Generate button. The container is
    // revealed server-side once it holds a value (saved here, or freshly
    // generated in self::ajaxCallback()). The editor stays in the DOM while
    // hidden so the AJAX callback can inject into it and the value still
    // submits. '.hidden' is core's system/base display:none utility.
    $wrapper['summary'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => $saved_value === '' ? ['hidden'] : [],
      ],
      '#weight' => 0,
    ];
    $summary = &$wrapper['summary'];

    // Label lives outside the summary container so it is always visible, even
    // before a summary has been generated.
    $wrapper['field_label'] = [
      '#type' => 'html_tag',
      '#tag' => 'label',
      '#value' => $this->t('AI summary', options: ['context' => 'helfi_ai']),
      '#attributes' => ['class' => ['form-item__label']],
      '#weight' => -10,
    ];

    $summary['value'] = [
      '#type' => 'text_format',
      '#title' => $this->t('AI summary', options: ['context' => 'helfi_ai']),
      '#title_display' => 'invisible',
      '#default_value' => $saved_value,
      '#format' => self::TEXT_FORMAT,
      '#allowed_formats' => [self::TEXT_FORMAT],
      '#rows' => 6,
      '#weight' => 0,
      '#after_build' => [[static::class, 'removeFormatHelp']],
    ];

    $wrapper['generate'] = $this->generateButton($saved_value !== '', $field_name, $delta, $wrapper_id);

    $wrapper['description'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $saved_value === ''
        ? $this->t('AI generates a summary of the page content as a short list of bullet points. Review the summary and edit it if needed. Keep the bullet points.', options: ['context' => 'helfi_ai'])
        : $this->t('Generate a new AI summary. It will replace the previous summary.', options: ['context' => 'helfi_ai']),
      '#attributes' => ['class' => ['description', 'form-item__description']],
      '#weight' => 100,
    ];

    return $element;
  }

  /**
   * Builds the Generate / Regenerate AJAX button.
   *
   * @param bool $has_value
   *   Whether the field already holds a summary (controls the label).
   * @param string $field_name
   *   Machine name of the field.
   * @param int $delta
   *   Field delta.
   * @param string $wrapper_id
   *   HTML id of the AJAX wrapper element.
   *
   * @return array<string, mixed>
   *   Render array for the button.
   */
  private function generateButton(bool $has_value, string $field_name, int $delta, string $wrapper_id): array {
    $ctx = ['context' => 'helfi_ai'];
    $button = [
      '#type' => 'button',
      '#value' => $has_value
        ? $this->t('Regenerate AI summary', options: $ctx)
        : $this->t('Generate AI summary', options: $ctx),
      '#name' => 'ai_summary_generate_' . $field_name . '_' . $delta,
      '#weight' => 50,
      '#ajax' => [
        'callback' => [static::class, 'ajaxCallback'],
        'wrapper' => $wrapper_id,
        // Buttons default to the 'mousedown' AJAX event; the confirm behavior
        // listens on 'click', which fires later. Bind AJAX to 'click' too so a
        // declined confirm can cancel the request before it starts.
        'event' => 'click',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Generating summary…', options: $ctx),
        ],
      ],
    ];

    // A summary that already exists may have been reviewed or hand-edited;
    // regenerating overwrites it. The confirm behavior loads on every instance
    // but only acts when the button carries data-helfi-ai-summary-confirm. That marker
    // is set whenever the button is a "regenerate": here when a saved value
    // exists, and in self::ajaxCallback() after a fresh generation (covering a
    // summary created earlier in this same unsaved session).
    $button['#attached']['library'][] = 'helfi_ai/ai_summary_confirm';
    if ($has_value) {
      $button['#attributes']['data-helfi-ai-summary-confirm'] = $this->t('Regenerating replaces the current AI summary, including any manual changes. Continue?', options: $ctx);
    }

    return $button;
  }

  /**
   * After-build callback: strips the "About text formats" help link.
   *
   * Filter_process_format() always appends a format[help] child containing a
   * "More information about text formats" link. It cannot be removed through
   * the UI or field settings, so we unset it here after the element is built.
   *
   * @param array<string, mixed> $element
   *   The processed text_format element.
   * @param \Drupal\Core\Form\FormStateInterface $_form_state
   *   The current form state (unused, required by after_build signature).
   *
   * @return array<string, mixed>
   *   The element with the help link removed.
   */
  public static function removeFormatHelp(array $element, FormStateInterface $_form_state): array {
    unset($element['format']['help']);
    return $element;
  }

  /**
   * AJAX callback: summarizes live form state and fills the field.
   *
   * Runs as the callback of a plain button, so it executes regardless of
   * validation and sees the full submitted values. Builds the unsaved entity,
   * asks the generator for a summary, and replaces the wrapper with the value
   * injected into the editable element.
   *
   * @param array<string, mixed> $form
   *   The (rebuilt, processed) form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Response replacing the widget wrapper.
   */
  public static function ajaxCallback(array &$form, FormStateInterface $form_state): AjaxResponse {
    $trigger = $form_state->getTriggeringElement();
    // The button is a child of ajax_wrapper; slice off the button key.
    $parents = array_slice($trigger['#array_parents'] ?? [], 0, -1);
    $wrapper = NestedArray::getValue($form, $parents);
    $wrapper_id = $wrapper['#attributes']['id'] ?? '';

    $summary = self::generateSummary($form, $form_state);

    if ($summary !== NULL && $summary !== '') {
      // Inject the generated value into the processed text_format textarea
      // (text_format expands to a 'value' textarea child after processing).
      if (isset($wrapper['summary']['value']['value'])) {
        $wrapper['summary']['value']['value']['#value'] = $summary;
      }
      // Reveal the editor now that it holds a summary.
      if (isset($wrapper['summary']['#attributes']['class'])) {
        $wrapper['summary']['#attributes']['class'] = array_values(
          array_diff($wrapper['summary']['#attributes']['class'], ['hidden']),
        );
      }
      // Once there is a value, the action becomes a regenerate. Relabel it and
      // add the confirm marker so a later click in this same session does not
      // silently overwrite the summary just generated.
      if (isset($wrapper['generate'])) {
        $translation = \Drupal::translation();
        $wrapper['generate']['#value'] = $translation
          ->translate('Regenerate AI summary', [], ['context' => 'helfi_ai']);
        $wrapper['generate']['#attributes']['data-helfi-ai-summary-confirm'] = (string) $translation
          ->translate('Regenerating replaces the current AI summary, including any manual changes. Continue?', [], ['context' => 'helfi_ai']);
      }
      if (isset($wrapper['description'])) {
        $wrapper['description']['#value'] = \Drupal::translation()
          ->translate('Generate a new AI summary. It will replace the previous summary.', [], ['context' => 'helfi_ai']);
      }
    }
    else {
      // Generation produced nothing: show an inline error and leave the field
      // unchanged.
      $wrapper['error'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => \Drupal::translation()
          ->translate('Could not generate a summary. Add some page content and make sure the AI provider is configured.', [], ['context' => 'helfi_ai']),
        '#attributes' => ['class' => ['messages', 'messages--error']],
        '#weight' => -20,
      ];
    }

    return (new AjaxResponse())->addCommand(
      new ReplaceCommand('#' . $wrapper_id, $wrapper),
    );
  }

  /**
   * Builds the unsaved entity from current form state and generates a summary.
   *
   * @param array<string, mixed> $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return string|null
   *   The generated summary HTML, or NULL on failure.
   */
  private static function generateSummary(array &$form, FormStateInterface $form_state): ?string {
    $form_object = $form_state->getFormObject();
    if (!$form_object instanceof ContentEntityFormInterface) {
      return NULL;
    }
    $entity = $form_object->buildEntity($form, $form_state);
    if (!$entity instanceof ContentEntityInterface) {
      return NULL;
    }
    // This is a throwaway clone holding the editor's unsaved changes. Mark it
    // as a preview so the view builder renders the in-memory state and skips
    // the render cache — otherwise an existing node would render its saved
    // (cached) content instead of the current edits. in_preview is an
    // untyped dynamic property that core's NodeViewBuilder reads.
    // @phpstan-ignore-next-line
    $entity->in_preview = TRUE;
    return \Drupal::service(AiSummaryGenerator::class)
      ->generate($entity, $entity->language()->getId());
  }

}
