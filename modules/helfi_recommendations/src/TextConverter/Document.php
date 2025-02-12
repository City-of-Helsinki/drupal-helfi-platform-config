<?php

declare(strict_types=1);

namespace Drupal\helfi_recommendations\TextConverter;

use Drupal\Component\Render\MarkupInterface;
use Masterminds\HTML5;

/**
 * Helper class for processing rendered HTML.
 */
final class Document {

  /**
   * DOM Document.
   *
   * @var \DOMDocument|null
   */
  private ?\DOMDocument $document = NULL;

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Component\Render\MarkupInterface $markup
   *   The markup.
   */
  public function __construct(private readonly MarkupInterface $markup) {
  }

  /**
   * Strip nodes using XPath query.
   *
   * @param string $xpath
   *   XPath query.
   *
   * @return self
   *   Self.
   */
  public function stripNodes(string $xpath) : self {
    $document = $this->getDocument();

    // New-up an instance of our DOMXPath class.
    $query = new \DOMXPath($document);

    // Find all elements that match the xpath query.
    foreach ($query->query($xpath) as $node) {
      if ($node instanceof \DOMElement) {
        $node->parentNode->removeChild($node);
      }
      else {
        throw new \InvalidArgumentException("XPath query must target nodes");
      }
    }

    return $this;
  }

  /**
   * Returns markup.
   *
   * @return string
   *   The markup.
   */
  public function __toString() {
    if ($this->document) {
      return $this->document->saveHTML() ?? '';
    }

    return (string) $this->markup;
  }

  /**
   * Get current document.
   *
   * @return \DOMDocument
   *   The document.
   */
  private function getDocument() : \DOMDocument {
    if (!$this->document) {
      // Instantiate the HTML5 parser, but without the HTML5 namespace being
      // added to the DOM document.
      $html5 = new HTML5(['disable_html_ns' => TRUE, 'encoding' => 'UTF-8']);

      // Attach the provided HTML inside the body. Rely on the HTML5 parser to
      // close the body tag.
      $this->document = $html5->loadHTML('<head><meta charset="UTF-8"></head><body>' . $this->markup);
    }

    return $this->document;
  }

}
