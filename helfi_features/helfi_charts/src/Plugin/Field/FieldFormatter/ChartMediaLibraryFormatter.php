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
 *   id = "helfi_chart_media_library",
 *   label = @Translation("Chart - Media library"),
 *   field_types = {
 *     "link",
 *   }
 * )
 */
final class ChartMediaLibraryFormatter extends FormatterBase {

  use UrlParserTrait;

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) :array {
    $element = [];

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
        '#theme' => 'chart_iframe__media_library',
        '#url' => (string) $url,
      ];
    }

    return $element;
  }

}
