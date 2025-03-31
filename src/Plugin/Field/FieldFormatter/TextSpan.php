<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

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
  public function viewElements(FieldItemListInterface $items, $langcode) : array {
    $markup = [];

    foreach ($items as $delta => $item) {

      /** @var string $value */
      $value = $item->value;
      if (empty($value)) {
        $value = $item->getValue();
      }
      $markup[$delta] = [
        '#children' => Xss::filter($value, ['span']),
      ];
    }

    return $markup;
  }

}
