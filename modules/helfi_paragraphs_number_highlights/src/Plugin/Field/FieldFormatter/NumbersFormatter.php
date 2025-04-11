<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_number_highlights\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'numbers_item' field formatter.
 */
#[FieldFormatter(
  id: "numbers_item_formatter",
  label: new TranslatableMarkup("Number + Text (Default)", [], ['context' => 'Number highlights']),
  field_types: ["numbers_item"]
)]
class NumbersFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    foreach ($items as $delta => $item) {
      $elements[$delta] = [
        '#theme' => 'numbers_item',
        '#number' => $item->number,
        '#text' => $item->text,
        '#attributes' => [
          'class' => ['numbers-item__container'],
        ],
      ];
    }
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    return [];
  }

}
