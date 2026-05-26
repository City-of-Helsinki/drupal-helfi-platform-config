<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Pipeline;

use Drupal\helfi_platform_config\HeadingSlugger;

/**
 * Mirrors headingIdInjector.js server-side.
 */
class HeadingFragmentExtractor {

  /**
   * Walk the DOM and produce heading fragments.
   *
   * @return HeadingFragment[]
   *   One entry per h2–h6.
   */
  public static function extract(\DOMDocument $doc, string $langcode): array {
    $xpath = new \DOMXPath($doc);

    $main = self::findMainWrapper($xpath);
    if (!$main) {
      return [];
    }

    // Collect every id attribute on the whole page.
    $reserved = [];
    $idNodes = $xpath->query('//*[@id]');
    if ($idNodes) {
      foreach ($idNodes as $el) {
        if ($el instanceof \DOMElement) {
          $reserved[] = $el->getAttribute('id');
        }
      }
    }

    $slugger = new HeadingSlugger($langcode, $reserved);

    $headings = $xpath->query('.//h2 | .//h3 | .//h4 | .//h5 | .//h6', $main);
    if (!$headings) {
      return [];
    }

    $out = [];
    foreach ($headings as $h) {
      if (!$h instanceof \DOMElement) {
        continue;
      }

      $level = (int) substr($h->tagName, 1);
      $text = trim((string) $h->textContent);

      $heading = new Heading($text, $level);

      if (self::isExcluded($h)) {
        $out[] = new HeadingFragment($heading, NULL);
        continue;
      }

      $existing = $h->getAttribute('id');
      if ($existing !== '') {
        $out[] = new HeadingFragment($heading, $existing);
        continue;
      }

      $out[] = new HeadingFragment($heading, $slugger->slug($text));
    }

    return $out;
  }

  /**
   * Locate <main class="layout-main-wrapper"> if present.
   */
  private static function findMainWrapper(\DOMXPath $xpath): ?\DOMElement {
    $nodes = $xpath->query(
      "//main[contains(concat(' ', normalize-space(@class), ' '), ' layout-main-wrapper ')]"
    );
    if (!$nodes || $nodes->length === 0) {
      return NULL;
    }
    $node = $nodes->item(0);
    return $node instanceof \DOMElement ? $node : NULL;
  }

  /**
   * Check whether any ancestor has the hide-from-table-of-contents class.
   */
  private static function isExcluded(\DOMElement $node): bool {
    $cur = $node;
    while ($cur instanceof \DOMElement) {
      $class = $cur->getAttribute('class');
      if ($class !== '') {
        $padded = ' ' . preg_replace('/\s+/', ' ', $class) . ' ';
        if (str_contains($padded, ' hide-from-table-of-contents ')) {
          return TRUE;
        }
      }
      $parent = $cur->parentNode;
      $cur = $parent instanceof \DOMElement ? $parent : NULL;
    }
    return FALSE;
  }

}
