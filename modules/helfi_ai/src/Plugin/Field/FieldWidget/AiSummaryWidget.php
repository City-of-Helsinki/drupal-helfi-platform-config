<?php

declare(strict_types=1);

namespace Drupal\helfi_ai\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_ai\PreviewEntityBuilder;
use Drupal\helfi_ai\Service\AiGenerator;

/**
 * Widget for the AI summary field.
 */
#[FieldWidget(
  id: 'ai_summary',
  label: new TranslatableMarkup('AI Summary'),
  field_types: ['text_long'],
)]
final class AiSummaryWidget extends WidgetBase {

  /**
   * Text format used for AI summary content.
   */
  private const string TEXT_FORMAT = 'minimal';

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    return $field_definition->getName() === 'ai_summary';
  }

  /**
   * Reads the submitted summary value and sets it on the field.
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
    // Set the value only when submitted.
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

    $element['ajax_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => $wrapper_id],
      '#weight' => 0,
      'description' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $saved_value === ''
          ? $this->t('AI generates a summary of the page content as a short list of bullet points. Review the summary and edit it if needed. Keep the bullet points.', options: ['context' => 'helfi_ai'])
          : $this->t('Generate a new AI summary. It will replace the previous summary.', options: ['context' => 'helfi_ai']),
        '#attributes' => ['class' => ['description', 'form-item__description']],
        '#weight' => 100,
      ],
      'field_label' => [
        '#type' => 'html_tag',
        '#tag' => 'label',
        '#value' => $this->t('AI summary', options: ['context' => 'helfi_ai']),
        '#attributes' => ['class' => ['form-item__label']],
        '#weight' => -10,
      ],
      'generate' => $this->generateButton($saved_value !== '', $field_name, $delta, $wrapper_id),
      'summary' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => $saved_value === '' ? ['hidden'] : [],
        ],
        '#weight' => 0,
        'value' => [
          '#type' => 'text_format',
          '#title' => $this->t('AI summary', options: ['context' => 'helfi_ai']),
          '#title_display' => 'invisible',
          '#default_value' => $saved_value,
          '#format' => self::TEXT_FORMAT,
          '#allowed_formats' => [self::TEXT_FORMAT],
          '#rows' => 6,
          '#weight' => 0,
          '#after_build' => [[self::class, 'removeFormatHelp']],
        ],
      ],
    ];

    return $element;
  }

  /**
   * Builds the Generate / Regenerate AJAX button.
   *
   * @param bool $has_value
   *   Whether the field already holds a summary.
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
        'callback' => [self::class, 'ajaxCallback'],
        'wrapper' => $wrapper_id,
        'event' => 'click',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Generating summary…', options: $ctx),
        ],
      ],
    ];

    $button['#attached']['library'][] = 'helfi_ai/ai_summary_confirm';
    if ($has_value) {
      $button['#attributes']['data-helfi-ai-summary-confirm'] = $this->t('Regenerating replaces the current AI summary, including any manual changes. Continue?', options: $ctx);
    }

    return $button;
  }

  /**
   * Strips the text format help link from the element.
   *
   * @param array<string, mixed> $element
   *   The processed text_format element.
   * @param \Drupal\Core\Form\FormStateInterface $_form_state
   *   The current form state.
   *
   * @return array<string, mixed>
   *   The element with the help link removed.
   */
  public static function removeFormatHelp(array $element, FormStateInterface $_form_state): array {
    unset($element['format']['help']);
    return $element;
  }

  /**
   * Summarizes the live form state and fills the field.
   *
   * @param array<string, mixed> $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Response replacing the widget wrapper.
   */
  public static function ajaxCallback(array &$form, FormStateInterface $form_state): AjaxResponse {
    $trigger = $form_state->getTriggeringElement();
    // Slice off the button key to reach the wrapper.
    $parents = array_slice($trigger['#array_parents'] ?? [], 0, -1);
    $wrapper = NestedArray::getValue($form, $parents);
    $wrapper_id = $wrapper['#attributes']['id'] ?? '';

    $entity = PreviewEntityBuilder::fromFormState($form, $form_state);

    $summary = \Drupal::service(AiGenerator::class)
      ->generateSummary($entity);

    if ($summary !== NULL && $summary !== '') {
      // Inject the generated value into the editor textarea.
      if (isset($wrapper['summary']['value']['value'])) {
        $wrapper['summary']['value']['value']['#value'] = $summary;
      }
      // Reveal the editor now that it holds a summary.
      if (isset($wrapper['summary']['#attributes']['class'])) {
        $wrapper['summary']['#attributes']['class'] = array_values(
          array_diff($wrapper['summary']['#attributes']['class'], ['hidden']),
        );
      }

      if (isset($wrapper['generate'])) {
        $wrapper['generate']['#value'] = new TranslatableMarkup('Regenerate AI summary', [], ['context' => 'helfi_ai']);
        $wrapper['generate']['#attributes']['data-helfi-ai-summary-confirm'] = (string) new TranslatableMarkup('Regenerating replaces the current AI summary, including any manual changes. Continue?', [], ['context' => 'helfi_ai']);
      }
      if (isset($wrapper['description'])) {
        $wrapper['description']['#value'] = new TranslatableMarkup('Generate a new AI summary. It will replace the previous summary.', [], ['context' => 'helfi_ai']);
      }
    }
    else {
      // Show an inline error when generation produced nothing.
      $wrapper['error'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => new TranslatableMarkup('Could not generate a summary. Add some page content and make sure the AI provider is configured.', [], ['context' => 'helfi_ai']),
        '#attributes' => ['class' => ['messages', 'messages--error']],
        '#weight' => -20,
      ];
    }

    return (new AjaxResponse())->addCommand(
      new ReplaceCommand('#' . $wrapper_id, $wrapper),
    );
  }

}
