<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_number_highlights\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines the widget for the numbers_item field type.
 */
#[FieldWidget(
  id: "numbers_item_widget",
  label: new TranslatableMarkup("Number + Text (Default)", [], ['context' => 'Number highlights']),
  field_types: ["numbers_item"]
)]
class NumbersWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $element['number'] = [
      '#type' => 'textfield',
      '#title' => new TranslatableMarkup('Number', [], ['context' => 'Number highlights']),
      '#default_value' => $items[$delta]->number ?? '',
      '#size' => 7,
      '#maxlength' => 7,
      '#character_counter' => TRUE,
      '#counter_step' => 0,
      '#counter_total' => 7,
      '#counter_type' => 'multifield',
    ];

    $element['text'] = [
      '#type' => 'textfield',
      '#title' => new TranslatableMarkup('Text', [], ['context' => 'Number highlights']),
      '#default_value' => $items[$delta]->text ?? '',
      '#size' => 60,
      '#maxlength' => 60,
      '#character_counter' => TRUE,
      '#counter_step' => 60,
      '#counter_total' => 60,
      '#counter_type' => 'multifield',
    ];

    return $element;
  }

}
