<?php

declare(strict_types=1);

namespace Drupal\helfi_search;

/**
 * Canonicalizes brand terms in a query before it is embedded.
 *
 * Short queries are tokenized by the embedding model differently depending on
 * the casing, which makes results for some terms inconsistent. Rewriting
 * known terms to their canonical stylization before embedding aligns the
 * query's tokenization with how the term appears in indexed content.
 */
final class QueryRewriter {

  /**
   * Rewrite occurrences of canonical brand terms to their configured casing.
   *
   * Each term is matched case-insensitively and as a whole word, then replaced
   * with the term exactly as listed.
   *
   * @param string $query
   *   The user-supplied query.
   * @param array<int, string> $terms
   *   Canonical brand terms, typically from the canonical_terms config value.
   *
   * @return string
   *   The query with canonical casing applied.
   */
  public static function rewrite(string $query, array $terms): string {
    if ($query === '' || $terms === []) {
      return $query;
    }

    $patterns = [];
    $replacements = [];
    foreach ($terms as $term) {
      $term = trim($term);
      if ($term === '') {
        continue;
      }
      $patterns[] = '/\b' . preg_quote($term, '/') . '\b/iu';
      $replacements[] = $term;
    }

    if ($patterns === []) {
      return $query;
    }

    return preg_replace($patterns, $replacements, $query) ?? $query;
  }

}
