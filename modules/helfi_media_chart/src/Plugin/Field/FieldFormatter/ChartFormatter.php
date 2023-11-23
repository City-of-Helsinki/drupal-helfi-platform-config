<?php

declare(strict_types = 1);

namespace Drupal\helfi_media_chart\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Utility\Error;
use Drupal\helfi_media_chart\UrlParserTrait;

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
        // @todo Use dependency injection.
        // phpcs:ignore DrupalPractice.Objects.GlobalDrupal.GlobalDrupal
        $logger = \Drupal::logger('helfi_chart');
        Error::logException($logger, $e);
        continue;
      }
      $element[$delta] = [
        '#theme' => 'chart_iframe',
        '#title' => $entity->field_helfi_chart_title->value,
        '#url' => (string) $url,
        '#domain' => $url->getHost(),
        '#attached' => [
          'library' => [
            'helfi_media_chart/helfi_charts',
          ],
        ],
      ];
    }

    return $element;
  }

}
