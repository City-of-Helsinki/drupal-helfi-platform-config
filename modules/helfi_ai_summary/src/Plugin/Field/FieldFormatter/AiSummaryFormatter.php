<?php

declare(strict_types=1);

namespace Drupal\helfi_ai_summary\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Renders the AI summary plain-text value as an HTML bullet list.
 */
#[FieldFormatter(
  id: 'ai_summary',
  label: new TranslatableMarkup('AI Summary (bullet list)'),
  field_types: ['string_long'],
)]
final class AiSummaryFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    return $field_definition->getName() === 'field_ai_summary';
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];

    foreach ($items as $delta => $item) {
      if ($item->isEmpty()) {
        continue;
      }

      $lines = array_filter(
        array_map('trim', explode("\n", (string) $item->value)),
        fn(string $line) => $line !== '',
      );

      if (empty($lines)) {
        continue;
      }

      $list_items = array_map(
        fn(string $line) => ['#plain_text' => $line],
        $lines,
      );

      $elements[$delta] = [
        '#theme' => 'item_list',
        '#items' => $list_items,
        '#type' => 'ul',
      ];
    }

    return $elements;
  }

}
