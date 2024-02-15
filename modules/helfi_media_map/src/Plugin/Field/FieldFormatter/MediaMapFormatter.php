<?php

declare(strict_types=1);

namespace Drupal\helfi_media_map\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\helfi_media_map\UrlParserTrait;

/**
 * Plugin implementation of the 'Map' formatter.
 *
 * @FieldFormatter(
 *   id = "hel_media_map",
 *   label = @Translation("Map"),
 *   field_types = {
 *     "link",
 *   }
 * )
 */
final class MediaMapFormatter extends FormatterBase {

  use UrlParserTrait;

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() : array {
    return [
      'link_title' => 'Open larger map',
    ] + parent::defaultSettings();
  }

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
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) : array {
    $element = [];

    foreach ($items as $delta => $item) {
      ['uri' => $uri, 'title' => $title] = $item->getValue();
      $link = $this->getMapUrl($uri);

      $element[$delta] = [
        '#theme' => 'helfi_media_map',
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

      if ($link_title = $this->getSetting('link_title')) {
        $element[$delta]['#link'] = [
          '#type' => 'link',
          '#title' => $link_title,
          '#url' => Url::fromUri($link),
          '#attributes' => [],
        ];
      }
    }

    return $element;
  }

}
