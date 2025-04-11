<?php

namespace Drupal\helfi_paragraphs_number_highlights\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines the widget for the numbers_item field type.
 */
#[FieldWidget(
  id: "numbers_item_widget",
  label: new TranslatableMarkup("Number + Text (Default)"),
  field_types: ["numbers_item"]
)]
class NumbersWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'placeholder_number' => '',
      'placeholder_text' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements = [];

    $elements['placeholder_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Placeholder for Number'),
      '#default_value' => $this->getSetting('placeholder_number'),
    ];
    $elements['placeholder_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Placeholder for Text'),
      '#default_value' => $this->getSetting('placeholder_text'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = [];

    $summary[] = $this->t('Number placeholder: @placeholder', ['@placeholder' => $this->getSetting('placeholder_number')]);
    $summary[] = $this->t('Text placeholder: @placeholder', ['@placeholder' => $this->getSetting('placeholder_text')]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $element['number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Number'),
      '#default_value' => $items[$delta]->number ?? '',
      '#size' => 6,
      '#maxlength' => 6,
      '#placeholder' => $this->getSetting('placeholder_number'),
    ];

    $element['text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Text'),
      '#default_value' => $items[$delta]->text ?? '',
      '#size' => 60,
      '#maxlength' => 60,
      '#placeholder' => $this->getSetting('placeholder_text'),
    ];

    return $element;
  }

}
