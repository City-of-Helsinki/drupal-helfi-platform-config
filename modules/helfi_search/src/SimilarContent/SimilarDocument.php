<?php

declare(strict_types=1);

namespace Drupal\helfi_search\SimilarContent;

/**
 * A document from the shared embeddings index.
 */
final readonly class SimilarDocument {

  public function __construct(
    public string $id,
    public string $label,
    public string $url,
    public string $instance,
    public string $markdown,
  ) {
  }

  /**
   * Builds the document from an Elasticsearch hit.
   *
   * @param array<string, mixed> $hit
   *   The hit.
   */
  public static function fromHit(array $hit): self {
    $source = is_array($hit['_source'] ?? NULL) ? $hit['_source'] : [];

    return new self(
      id: is_string($hit['_id'] ?? NULL) ? $hit['_id'] : '',
      label: self::firstString($source['label'] ?? NULL),
      url: self::firstString($source['url'] ?? NULL),
      instance: self::firstString($source['instance'] ?? NULL),
      markdown: self::firstString($source['markdown'] ?? NULL),
    );
  }

  /**
   * Reads a Search API field value, which is stored as a list.
   */
  private static function firstString(mixed $value): string {
    if (is_array($value)) {
      $value = array_first($value);
    }

    return is_scalar($value) ? (string) $value : '';
  }

}
