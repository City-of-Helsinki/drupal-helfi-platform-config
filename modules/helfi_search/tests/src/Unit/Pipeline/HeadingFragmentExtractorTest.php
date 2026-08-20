<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_search\Unit\Pipeline;

use Drupal\helfi_search\Pipeline\HeadingFragmentExtractor;
use Drupal\Tests\UnitTestCase;
use Masterminds\HTML5;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for HeadingFragmentExtractor.
 */
#[Group('helfi_search')]
class HeadingFragmentExtractorTest extends UnitTestCase {

  /**
   * Tests basic h2/h3 extraction.
   */
  public function testExtractsHeadingsInOrder(): void {
    $doc = $this->parse(<<<HTML
      <main class="layout-main-wrapper">
        <h2 id="visible" tabindex="-1">Visible</h2>
        <p>body</p>
        <h3 id="foobar" tabindex="-1">Foobar</h3>
        <h2 id="custom-anchor" tabindex="-1">Pretty Title</h2>
      </main>
    HTML);

    $result = HeadingFragmentExtractor::extract($doc);

    $this->assertCount(3, $result);
    $this->assertSame('visible', $result[0]->fragment);
    $this->assertSame('Visible', $result[0]->heading->title);
    $this->assertSame(2, $result[0]->heading->level);
    $this->assertSame('foobar', $result[1]->fragment);
    $this->assertSame(3, $result[1]->heading->level);
    $this->assertSame('custom-anchor', $result[2]->fragment);
  }

  /**
   * Tests a heading the injector skipped yields no fragment.
   *
   * The injector leaves excluded headings alone, so the absence of an id is
   * what marks a heading as not linkable.
   */
  public function testHeadingWithoutIdHasNoFragment(): void {
    $doc = $this->parse(<<<HTML
      <main class="layout-main-wrapper">
        <div class="hide-from-table-of-contents">
          <h2>Internal widget</h2>
        </div>
        <h2 id="visible" tabindex="-1">Visible</h2>
      </main>
    HTML);

    $result = HeadingFragmentExtractor::extract($doc);

    $this->assertCount(2, $result);
    $this->assertNull($result[0]->fragment);
    $this->assertSame('Internal widget', $result[0]->heading->title);
    $this->assertSame('visible', $result[1]->fragment);
  }

  /**
   * Tests headings outside the main wrapper are ignored.
   */
  public function testIgnoresHeadingsOutsideMainWrapper(): void {
    $doc = $this->parse(<<<HTML
      <header><h2 id="site-name">Site name</h2></header>
      <main class="layout-main-wrapper">
        <h2 id="content">Content</h2>
      </main>
      <footer><h2 id="contact">Contact</h2></footer>
    HTML);

    $result = HeadingFragmentExtractor::extract($doc);

    $this->assertCount(1, $result);
    $this->assertSame('content', $result[0]->fragment);
  }

  /**
   * Parse an HTML snippet into the DOMDocument.
   */
  private function parse(string $html): \DOMDocument {
    $html5 = new HTML5(['disable_html_ns' => TRUE, 'encoding' => 'UTF-8']);
    return $html5->loadHTML('<!doctype html><html><body>' . $html);
  }

}
