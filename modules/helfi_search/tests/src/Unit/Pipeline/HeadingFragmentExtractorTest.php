<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_search\Unit\Pipeline;

use Drupal\helfi_search\Pipeline\HeadingFragmentExtractor;
use Drupal\Tests\UnitTestCase;
use Masterminds\HTML5;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for HeadingFragmentExtractor.
 *
 * @see assets/js/headingIdInjector.js
 */
#[Group('helfi_search')]
class HeadingFragmentExtractorTest extends UnitTestCase {

  /**
   * Tests basic h2/h3 extraction in document order with slugs.
   */
  public function testExtractsHeadingsInOrder(): void {
    $doc = $this->parse(<<<HTML
      <main class="layout-main-wrapper">
        <h2>Visible</h2>
        <p>body</p>
        <h3>Foobar</h3>
        <!-- headings inside .hide-from-table-of-contents get NULL fragment -->
        <div class="hide-from-table-of-contents">
          <h2>Internal widget</h2>
        </div>
        <!-- reserved ids -->
        <aside id="intro">sidebar</aside>
        <h2>Intro</h2>
        <!-- existing id attribute is used verbatim. -->
        <h2 id="custom-anchor">Pretty Title</h2>
      </main>
    HTML);

    $result = HeadingFragmentExtractor::extract($doc, 'en');

    $this->assertCount(5, $result);
    $this->assertSame('visible', $result[0]->fragment);
    $this->assertSame('foobar', $result[1]->fragment);
    $this->assertNull($result[2]->fragment);
    $this->assertSame('intro-1', $result[3]->fragment);
    $this->assertSame('custom-anchor', $result[4]->fragment);
  }

  /**
   * Tests dedup of repeated heading text matches the JS behaviour.
   */
  public function testDuplicateHeadingsGetIncrementingSuffixes(): void {
    $doc = $this->parse(<<<HTML
      <main class="layout-main-wrapper">
        <h2>Section</h2>
        <h2>Section</h2>
        <h2>Section</h2>
      </main>
    HTML);

    $result = HeadingFragmentExtractor::extract($doc, 'en');

    $fragments = array_map(static fn ($entry) => $entry->fragment, $result);
    $this->assertSame(['section', 'section-2', 'section-3'], $fragments);
  }

  /**
   * Parse an HTML snippet into the DOMDocument.
   */
  private function parse(string $html): \DOMDocument {
    $html5 = new HTML5(['disable_html_ns' => TRUE, 'encoding' => 'UTF-8']);
    return $html5->loadHTML('<!doctype html><html><body>' . $html);
  }

}
