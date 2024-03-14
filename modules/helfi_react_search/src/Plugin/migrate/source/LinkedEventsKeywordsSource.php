<?php

declare(strict_types=1);

namespace Drupal\helfi_react_search\Plugin\migrate\source;

use Drupal\helfi_api_base\Plugin\migrate\source\HttpSourcePluginBase;

/**
 * Source plugin for retrieving keyword sets from linked events.
 *
 * @MigrateSource(
 *   id = "linked_events_keywords"
 * )
 */
final class LinkedEventsKeywordsSource extends HttpSourcePluginBase {

  /**
   * {@inheritDoc}
   */
  protected function initializeListIterator(): \Iterator {
    foreach ($this->getKeywords() as $keyword) {
      // Generate one row for each language.
      foreach ($keyword['name'] as $langcode => $name) {
        yield array_merge($keyword, [
          // Add langcode field.
          'language' => $langcode,
          // Replace name field with this language.
          'name' => $name,
        ]);
      }
    }
  }

  /**
   * {@inheritDoc}
   */
  public function fields(): array {
    return [];
  }

  /**
   * {@inheritDoc}
   */
  public function getIds(): array {
    return [
      'id' => [
        'type' => 'string',
      ],
      'language' => [
        'type' => 'string',
      ],
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function __toString(): string {
    return 'LinkedEventsKeywordsSource';
  }

  /**
   * Get keywords from linked events api.
   *
   * @return \Generator
   *   Parsed responses from keyword endpoint.
   */
  private function getKeywords() : \Generator {
    $next_url = $this->getCanonicalBaseUrl();

    do {
      $result = $this->getContent($next_url);
      $next_url = $result['meta']['next'] ?? NULL;

      if (is_array($result['data'])) {
        yield from $result['data'];
      }
    } while (!is_null($next_url));
  }

}
