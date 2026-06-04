<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Pipeline;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Site\Settings;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

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
    'iframe', 'embed', 'object', 'noscript', 'time',
  ];

  /**
   * Element IDs to remove.
   */
  private const array REMOVE_IDS = [
    'helfi-survey__container',
  ];

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    #[Autowire(service: 'settings')] private readonly Settings $settings,
  ) {
  }

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
   *
   * @param \DOMNodeList<\DOMNode> $nodes
   *   List of nodes to remove.
   */
  private function removeNodeList(\DOMNodeList $nodes): void {
    foreach (iterator_to_array($nodes) as $node) {
      $node->parentNode?->removeChild($node);
    }
  }

  /**
   * Check whether a node is inside a heading element (h1–h6).
   */
  private function isInsideHeading(\DOMNode $node): bool {
    $parent = $node->parentNode;
    while ($parent) {
      if ($parent instanceof \DOMElement && preg_match('/^h[1-6]$/i', $parent->tagName)) {
        return TRUE;
      }
      $parent = $parent->parentNode;
    }
    return FALSE;
  }

  /**
   * Replace a node with its children, preserving their content.
   */
  private function unwrapNode(\DOMNode $node): void {
    $parent = $node->parentNode;
    if (!$parent) {
      return;
    }
    while ($node->firstChild) {
      $parent->insertBefore($node->firstChild, $node);
    }
    $parent->removeChild($node);
  }

  /**
   * Remove non-content tags and elements with boilerplate CSS classes.
   */
  private function removeNonContentElements(\DOMDocument $doc): void {
    foreach (self::REMOVE_TAGS as $tag) {
      foreach (iterator_to_array($doc->getElementsByTagName($tag)) as $node) {
        if ($this->isInsideHeading($node)) {
          $this->unwrapNode($node);
        }
        else {
          $node->parentNode?->removeChild($node);
        }
      }
    }

    $xpath = new \DOMXPath($doc);

    // Anything explicitly hidden from assistive tech is by definition not
    // body content. This catches placeholder "ghost" cards rendered before
    // a JS/HTMX widget swaps in real content, plus decorative icons
    // independent of which CSS class the widget happens to use.
    if ($hidden = $xpath->query('//*[@aria-hidden="true"]')) {
      $this->removeNodeList($hidden);
    }

    // Uses the whitespace-boundary trick: pad @class with spaces so that
    // contains() matches whole words only (e.g. " visually-hidden ").
    $ignoredClasses = [
      ...$this->configFactory->get('helfi_search.settings')->get('ignored_classes') ?? [],
      ...$this->settings->get('helfi_search_additional_ignored_classes', []),
    ];
    foreach ($ignoredClasses as $class) {
      $elements = $xpath->query(
        '//*[contains(concat(" ", normalize-space(@class), " "), " ' . $class . ' ")]'
      );
      if ($elements) {
        $this->removeNodeList($elements);
      }
    }

    foreach (self::REMOVE_IDS as $id) {
      $elements = $xpath->query('//*[@id="' . $id . '"]');
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
   * Iteratively remove wrappers and list containers with no real content.
   *
   * Treats whitespace-only text as empty so that prior cleanup passes
   * (link unwrapping, image stripping, hidden-element removal) can leave
   * an outer wrapper as a candidate for removal.
   */
  private function removeEmptyWrappers(\DOMDocument $doc): void {
    $tagFilter = "(local-name()='div' or local-name()='span' or local-name()='li' or local-name()='ul' or local-name()='ol')";
    do {
      $changed = FALSE;
      $xpath = new \DOMXPath($doc);
      // $tagFilter: must be one of the five wrapper tags.
      // not(*): has no child elements.
      // not(normalize-space()): has no text after collapsing whitespace.
      $emptyNodes = $xpath->query("//*[$tagFilter and not(*) and not(normalize-space())]");

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
