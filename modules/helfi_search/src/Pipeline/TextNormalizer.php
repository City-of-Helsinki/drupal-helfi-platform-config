<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Pipeline;

/**
 * Applies minimal text normalization.
 */
class TextNormalizer {

  /**
   * Normalize text.
   *
   * @param string $text
   *   Text to normalize.
   *
   * @return string
   *   Normalized text.
   */
  public function normalize(string $text): string {
    if (empty($text)) {
      return $text;
    }

    // Collapse multiple horizontal whitespace chars (non-newline) to one space.
    $text = preg_replace('/[^\S\n]+/', ' ', $text);

    // Remove spaces immediately before or after newlines.
    $text = preg_replace('/ *\n */', "\n", $text);

    // Limit consecutive newlines to a maximum of two.
    $text = preg_replace('/\n{3,}/', "\n\n", $text);

    return trim($text);
  }

}
