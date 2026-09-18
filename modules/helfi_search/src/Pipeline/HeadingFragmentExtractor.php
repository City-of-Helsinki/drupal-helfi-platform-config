<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Pipeline;

/**
 * Reads the heading anchors injected by HeadingIdInjector.
 *
 * @see \Drupal\helfi_platform_config\HeadingIdInjector
 */
class HeadingFragmentExtractor {

  /**
   * Walk the DOM and produce heading fragments.
   *
   * @return HeadingFragment[]
   *   One entry per h2–h6.
   */
  public static function extract(\DOMDocument $doc): array {
    $xpath = new \DOMXPath($doc);

    $main = self::findMainWrapper($xpath);
    if (!$main) {
      return [];
    }

    $headings = $xpath->query('.//h2 | .//h3 | .//h4 | .//h5 | .//h6', $main);
    if (!$headings) {
      return [];
    }

    $out = [];
    foreach ($headings as $h) {
      if (!$h instanceof \DOMElement) {
        continue;
      }

      $heading = new Heading(
        trim((string) $h->textContent),
        (int) substr($h->tagName, 1),
      );

      $out[] = new HeadingFragment($heading, $h->getAttribute('id') ?: NULL);
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

}
