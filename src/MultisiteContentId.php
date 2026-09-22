<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config;

/**
 * Converts shared-index document ids to split canonical path segments.
 *
 * Elasticsearch _id values look like "site_etusivu/entity:node/8420:en".
 * Drupal routes cannot put slashes in a single parameter, so the canonical
 * path is /helfi-multisite-content/{instance}/{datasource}/{item} with colons
 * replaced by a dash in datasource and item only.
 */
final class MultisiteContentId {

  public const string ENTITY_TYPE_ID = 'helfi_multisite_content';

  public const string PATH_PREFIX = 'helfi-multisite-content';

  /**
   * Splits a source id into canonical path segments.
   *
   * @param string $source_id
   *   Elasticsearch document id.
   *
   * @return array{instance: string, datasource: string, item: string}|null
   *   Path parameters, or NULL if the id is not three slash-separated parts.
   */
  public static function toPathSegments(string $source_id): ?array {
    $parts = explode('/', $source_id);
    if (count($parts) !== 3 || in_array('', $parts, TRUE)) {
      return NULL;
    }

    return [
      'instance' => $parts[0],
      'datasource' => self::toUrlSegment($parts[1]),
      'item' => self::toUrlSegment($parts[2]),
    ];
  }

  /**
   * Builds a source id from canonical path segments.
   *
   * @param string $instance
   *   Instance segment, e.g. "site_etusivu".
   * @param string $datasource
   *   Datasource segment, e.g. "entity-node".
   * @param string $item
   *   Item segment, e.g. "8420-en".
   *
   * @return string
   *   Elasticsearch document id.
   */
  public static function fromPathSegments(string $instance, string $datasource, string $item): string {
    return implode('/', [
      $instance,
      self::fromUrlSegment($datasource),
      self::fromUrlSegment($item),
    ]);
  }

  /**
   * Extracts a source id from a helfi-multisite-content path.
   *
   * @param string $value
   *   User input such as "/fi/helfi-multisite-content/site_etusivu/entity-node/8420-en".
   *
   * @return string|null
   *   Elasticsearch document id, or NULL if the value is not that path.
   */
  public static function extractFromUserInput(string $value): ?string {
    $value = trim($value);
    if ($value === '') {
      return NULL;
    }

    $path = parse_url($value, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
      $path = $value;
    }

    $pattern = sprintf(
      '~^(?:/[a-z]{2})?/%s/([^/]+)/([^/]+)/([^/]+)$~',
      preg_quote(self::PATH_PREFIX, '~'),
    );
    if (preg_match($pattern, rawurldecode($path), $matches) !== 1) {
      return NULL;
    }

    return self::fromPathSegments($matches[1], $matches[2], $matches[3]);
  }

  /**
   * Canonical path template for entity link relations.
   *
   * @param string $suffix
   *   Optional suffix such as "/edit".
   *
   * @return string
   *   Path template.
   */
  public static function canonicalPathTemplate(string $suffix = ''): string {
    return '/' . self::PATH_PREFIX . '/{instance}/{datasource}/{item}' . $suffix;
  }

  /**
   * Replaces colons with dashes so the value can be used as a path segment.
   */
  private static function toUrlSegment(string $value): string {
    return str_replace(':', '-', $value);
  }

  /**
   * Restores dashes with colons.
   */
  private static function fromUrlSegment(string $value): string {
    return str_replace('-', ':', $value);
  }

}
