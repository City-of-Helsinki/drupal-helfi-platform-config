<?php

declare(strict_types = 1);

namespace Drupal\helfi_charts\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\helfi_charts\UrlParserTrait;

/**
 * Plugin implementation of the 'Chart' formatter.
 *
 * @FieldFormatter(
 *   id = "helfi_chart",
 *   label = @Translation("Chart"),
 *   field_types = {
 *     "link",
 *   }
 * )
 */
final class ChartFormatter extends FormatterBase {

  use UrlParserTrait;

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) :array {
    $element = [];
    $entity = $items->getEntity();

    foreach ($items as $delta => $item) {
      ['uri' => $uri] = $item->getValue();

      try {
        $url = $this->mediaUrlToUri($uri);
      }
      catch (\InvalidArgumentException $e) {
        watchdog_exception('helfi_chart', $e);

        continue;
      }
      $element[$delta] = [
        '#theme' => 'chart_iframe',
        '#title' => $entity->field_helfi_chart_title->value,
        '#url' => (string) $url,
        '#domain' => $url->getHost(),
        '#attached' => [
          'library' => [
            'helfi_charts/helfi_charts',
          ],
        ],
      ];
    }

    return $element;
  }

}
