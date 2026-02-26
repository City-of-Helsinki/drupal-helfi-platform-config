<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Pipeline;

/**
 * Cleans HTML by removing non-content elements before Markdown conversion.
 */
class HtmlCleaner {

  /**
   * Tags to remove entirely (including their children).
   */
  private const array REMOVE_TAGS = [
    'head', 'script', 'style', 'nav', 'footer', 'header',
    'aside', 'form', 'button', 'input', 'select', 'textarea',
    'iframe', 'embed', 'object', 'noscript',
  ];

  /**
   * Exact CSS classes to remove.
   */
  private const array REMOVE_CLASSES = [
    'is-hidden', 'visually-hidden', 'skip-link', 'table-of-contents',
    'component--recommendations', 'component--map',
  ];

  /**
   * Clean a parsed HTML document by removing non-content elements.
   *
   * @param \DOMDocument $doc
   *   Parsed HTML document to clean.
   *
   * @return string
   *   Cleaned HTML string.
   */
  public function clean(\DOMDocument $doc): string {
    // Remove non-content tags and boilerplate class patterns.
    $this->removeNonContentElements($doc);

    // Replace links with their text content (URLs are noise for embeddings).
    $this->unwrapLinks($doc);

    // Replace images with their alt text.
    $this->replaceImagesWithAlt($doc);

    // Remove empty div and span wrappers left after cleaning.
    $this->removeEmptyWrappers($doc);

    return $doc->saveHTML() ?: '';
  }

  /**
   * Remove all nodes in a DOMNodeList.
   *
   * Converts to array first since the live list changes during removal.
   */
  private function removeNodeList(\DOMNodeList $nodes): void {
    foreach (iterator_to_array($nodes) as $node) {
      $node->parentNode?->removeChild($node);
    }
  }

  /**
   * Remove non-content tags and elements with boilerplate CSS classes.
   */
  private function removeNonContentElements(\DOMDocument $doc): void {
    foreach (self::REMOVE_TAGS as $tag) {
      $this->removeNodeList($doc->getElementsByTagName($tag));
    }

    // Uses the whitespace-boundary trick: pad @class with spaces so that
    // contains() matches whole words only (e.g. " visually-hidden ").
    $xpath = new \DOMXPath($doc);
    foreach (self::REMOVE_CLASSES as $class) {
      $elements = $xpath->query(
        '//*[contains(concat(" ", normalize-space(@class), " "), " ' . $class . ' ")]'
      );
      if ($elements) {
        $this->removeNodeList($elements);
      }
    }
  }

  /**
   * Replace <a> tags with their text content.
   */
  private function unwrapLinks(\DOMDocument $doc): void {
    foreach (iterator_to_array($doc->getElementsByTagName('a')) as $link) {
      $parent = $link->parentNode;
      if (!$parent) {
        continue;
      }
      // Move all children (text nodes, inline elements) before the <a>.
      while ($link->firstChild) {
        $parent->insertBefore($link->firstChild, $link);
      }
      $parent->removeChild($link);
    }
  }

  /**
   * Replace <img> tags with a text node containing the alt text.
   */
  private function replaceImagesWithAlt(\DOMDocument $doc): void {
    foreach (iterator_to_array($doc->getElementsByTagName('img')) as $img) {
      $alt = trim($img->getAttribute('alt'));
      if ($alt !== '') {
        $img->parentNode?->removeChild($img);
      }
      else {
        $img->parentNode?->replaceChild($doc->createTextNode($alt), $img);
      }
    }
  }

  /**
   * Iteratively remove empty div and span wrappers.
   */
  private function removeEmptyWrappers(\DOMDocument $doc): void {
    do {
      $changed = FALSE;
      $xpath = new \DOMXPath($doc);
      $emptyNodes = $xpath->query('//div[not(node())] | //span[not(node())]');

      if (!$emptyNodes || $emptyNodes->length === 0) {
        break;
      }

      foreach (iterator_to_array($emptyNodes) as $node) {
        if ($node->parentNode) {
          $node->parentNode->removeChild($node);
          $changed = TRUE;
        }
      }
    } while ($changed);
  }

}
