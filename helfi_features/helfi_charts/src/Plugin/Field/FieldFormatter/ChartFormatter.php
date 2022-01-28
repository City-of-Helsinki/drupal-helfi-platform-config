<?php

declare(strict_types = 1);

namespace Drupal\helfi_charts\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
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

//  /**
//   * {@inheritdoc}
//   */
//  public static function defaultSettings() {
//    return parent::defaultSettings();
//  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) : array {
    $elements = parent::settingsForm($form, $form_state);

    $elements['link_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link title'),
      '#default_value' => $this->getSetting('link_title'),
    ];

    return $elements;
  }

  /**
   *
   *  Tämä kaikaa sitä transcript tekstikenttää
   *
   */


  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) :array {
    $element = [];

    foreach ($items as $delta => $item) {
      ['uri' => $uri, 'title' => $title] = $item->getValue();

      $link = $this->getMapUrl($uri);

      if ($link_title = $this->getSetting('link_title')) {
        $element[$delta]['#link'] = [
          '#type' => 'link',
          '#title' => $link_title,
          '#url' => Url::fromUri($link),
          '#attributes' => ['target' => '_blank'],
        ];
      }


      $element[$delta] = [
        '#theme' => 'helfi_chart',
        '#iframe' => [
          '#type' => 'html_tag',
          '#tag' => 'iframe',
          '#value' => '',
          '#attributes' => [
            'src' => $this->getEmbedUrl($uri),
            'frameborder' => 0,
            'title' => $title,
          ],
        ],
      ];

    }

    return $element;
  }

}
