<?php

declare(strict_types = 1);

namespace Drupal\helfi_paragraphs_hearings\Plugin\migrate\source;

use Drupal\helfi_api_base\Plugin\migrate\source\HttpSourcePluginBase;

/**
 * Source plugin for retrieving data from Kerrokantasi API.
 *
 * @MigrateSource(
 *   id = "helfi_hearings"
 * )
 */
final class Hearing extends HttpSourcePluginBase implements \Countable {

  /**
   * {@inheritdoc}
   */
  protected bool $useRequestCache = FALSE;

  /**
   * {@inheritdoc}
   */
  public function count($refresh = FALSE) : int {
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  protected function initializeListIterator(): \Iterator {
    $items = $this->fetchAllData($this->configuration['url']);

    foreach ($items as $item) {
      foreach (['fi', 'en', 'sv'] as $language) {
        if (!isset($item['title'][$language])) {
          continue;
        }
        $element = $item + [
          'langcode' => $language,
        ];

        foreach ($this->configuration['translatable_fields'] ?? [] as $field) {
          if (!isset($item[$field][$language])) {
            continue;
          }
          $element[$field] = $item[$field][$language];
        }

        if (isset($item['main_image'])) {
          $element += [
            'main_image_caption' => $item['main_image']['caption'][$language] ?? '',
            'main_image_url' => $item['main_image']['url'],
          ];
        }

        yield $element;
      }
    }
  }

  /**
   * Gets all available Hearing items.
   *
   * @param string $url
   *   The URL to fetch.
   *
   * @return \Iterator
   *   The pager data.
   */
  private function fetchAllData(string $url): \Iterator {
    $content = $this->getContent($url);

    foreach ($content['results'] as $item) {
      yield $item;
    }

    // Load data until there are no more pages left.
    if (!empty($content['next'])) {
      yield from $this->fetchAllData($content['next']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function fields() : array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function __toString() : string {
    return 'Hearing';
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() : array {
    return [
      'id' => [
        'type' => 'string',
      ],
    ];
  }

}
