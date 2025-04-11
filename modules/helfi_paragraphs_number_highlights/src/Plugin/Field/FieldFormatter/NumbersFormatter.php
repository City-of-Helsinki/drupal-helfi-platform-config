<?php

namespace Drupal\helfi_paragraphs_number_highlights\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'numbers_item' field formatter.
 */
#[FieldFormatter(
  id: "numbers_item_formatter",
  label: new TranslatableMarkup("Number + Text (Default)"),
  field_types: ["numbers_item"]
)]
class NumbersFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'display_number' => TRUE,
      'display_text' => TRUE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements = [];

    $elements['display_number'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display number'),
      '#default_value' => $this->getSetting('display_number'),
    ];
    $elements['display_text'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display text'),
      '#default_value' => $this->getSetting('display_text'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = [];

    if ($this->getSetting('display_number')) {
      $summary[] = $this->t('Displays the number.');
    }
    if ($this->getSetting('display_text')) {
      $summary[] = $this->t('Displays the text.');
    }
    if (!$summary) {
      $summary[] = $this->t('Nothing will be displayed.');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];

    $display_number = $this->getSetting('display_number');
    $display_text = $this->getSetting('display_text');

    foreach ($items as $delta => $item) {
      $output = [];

      if ($display_number && $item->number) {
        $output[] = [
          '#markup' => '<strong class="numbers-item-number">' . $item->number . '</strong>',
        ];
      }

      if ($display_text && $item->text) {
        $output[] = [
          '#markup' => '<span class="numbers-item-text">' . $item->text . '</span>',
        ];
      }

      $elements[$delta] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['numbers-item']],
        'content' => $output,
      ];
    }

    return $elements;
  }

}
