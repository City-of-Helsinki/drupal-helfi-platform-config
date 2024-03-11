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
    if (!isset($this->configuration['keyword_set'])) {
      throw new \InvalidArgumentException("Missing `keyword_set` parameter");
    }

    $keyword_set = $this->configuration['keyword_set'];

    foreach ($this->getKeywords($keyword_set) as $keyword) {
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
   * @param string $keyword_set
   *   Keyword set.
   *
   * @return \Generator
   *   Parsed responses from keyword endpoint.
   */
  private function getKeywords(string $keyword_set) : \Generator {
    $result = $this->getContent($this->buildCanonicalUrl("keyword_set/$keyword_set"));

    if (is_array($result['keywords'])) {
      foreach ($result['keywords'] as $keyword) {
        yield $this->getContent($keyword['@id']);
      }
    }
  }

}
