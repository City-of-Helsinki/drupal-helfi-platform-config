<?php

namespace Drupal\helfi_content\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin implementation of the 'Text with span' formatter.
 *
 * @FieldFormatter(
 *   id = "text_span",
 *   label = @Translation("Text with span"),
 *   field_types = {
 *     "text",
 *     "text_long",
 *     "text_with_summary",
 *     "string",
 *     "string_long",
 *   }
 * )
 */
class TextSpan extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = array();

    foreach ($items as $delta => $item) {

      $value = $item->value;
      if (empty($value)) {
        $value = $item->getValue();
      }
      $elements[$delta] = [
        '#children' => strip_tags($value, ['span']),
      ];
    }

    return $elements;
  }

}
