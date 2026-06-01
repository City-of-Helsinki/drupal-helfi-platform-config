<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_search\Unit\Pipeline;

use Drupal\helfi_search\Pipeline\HtmlCleaner;
use Drupal\Tests\helfi_search\Traits\IgnoredClassesConfigFactoryTrait;
use Drupal\Tests\UnitTestCase;
use Masterminds\HTML5;
use PHPUnit\Framework\Attributes\Group;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests for the HtmlCleaner pipeline service.
 */
#[Group('helfi_search')]
class HtmlCleanerTest extends UnitTestCase {

  use ProphecyTrait;
  use IgnoredClassesConfigFactoryTrait;

  /**
   * Gets service under test.
   *
   * @param string[] $ignoredClasses
   *   Classes the cleaner should treat as ignored. Defaults to the install set.
   */
  private function getSut(array $ignoredClasses = []): HtmlCleaner {
    return new HtmlCleaner(
      $this->stubIgnoredClassesConfigFactory($ignoredClasses),
      $this->stubIgnoredClassesSettings(),
    );
  }

  /**
   * Parse an HTML string into a DOMDocument.
   */
  private function parseHtml(string $html): \DOMDocument {
    $doc = new HTML5();
    return $doc->loadHTML($html);
  }

  /**
   * Tests that buttons outside headings are removed entirely.
   */
  public function testRemovesButtonsOutsideHeadings(): void {
    $html = '<div><button>Click me</button><p>Content</p></div>';
    $result = $this->getSut()->clean($this->parseHtml($html));

    $this->assertStringNotContainsString('Click me', $result);
    $this->assertStringNotContainsString('<button', $result);
    $this->assertStringContainsString('Content', $result);
  }

  /**
   * Tests accordion pattern with nested elements inside heading button.
   */
  public function testPreservesNestedContentInHeadingButton(): void {
    $html = '<h3><button><span>Section</span> Title</button></h3>';
    $result = $this->getSut()->clean($this->parseHtml($html));

    $this->assertStringContainsString('Section', $result);
    $this->assertStringContainsString('Title', $result);
    $this->assertStringNotContainsString('<button', $result);
  }

  /**
   * Tests that elements with hidden classes are removed.
   */
  public function testRemovesHiddenClassElements(): void {
    $html = '<div><p class="visually-hidden">Hidden</p><p>Visible</p></div>';
    $result = $this->getSut(['visually-hidden'])->clean($this->parseHtml($html));

    $this->assertStringNotContainsString('Hidden', $result);
    $this->assertStringContainsString('Visible', $result);
  }

  /**
   * Tests that links are unwrapped to plain text.
   */
  public function testUnwrapsLinks(): void {
    $html = '<p>See <a href="https://example.com">this page</a> for details.</p>';
    $result = $this->getSut()->clean($this->parseHtml($html));

    $this->assertStringContainsString('this page', $result);
    $this->assertStringNotContainsString('<a ', $result);
    $this->assertStringNotContainsString('href', $result);
  }

  /**
   * Tests that elements marked aria-hidden="true" are removed.
   *
   * The curated event/news lists render placeholder "ghost" cards while
   * waiting for HTMX to swap in real content. Those cards carry
   * aria-hidden="true" because they are not user-visible content.
   */
  public function testAriaHiddenElements(): void {
    $html = '<ul><li aria-hidden="true">Ghost</li><p aria-hidden="false">Visible</p><li>Real item</li></ul>';
    $result = $this->getSut()->clean($this->parseHtml($html));

    $this->assertStringNotContainsString('Ghost', $result);
    $this->assertStringContainsString('Visible', $result);
    $this->assertStringContainsString('Real item', $result);
  }

  /**
   * Tests that a list whose items were all removed is itself removed.
   *
   * Reproduces the curated-event-list shape: a <ul> of aria-hidden ghost
   * cards.
   */
  public function testRemovesListWithOnlyAriaHiddenItems(): void {
    $html = <<<'HTML'
<div>
  <h2>Suositellut tapahtumat</h2>
  <ul class="curated-event-list__events">
    <li aria-hidden="true"><div class="card__text"></div></li>
    <li aria-hidden="true"><div class="card__text"></div></li>
    <li aria-hidden="true"><div class="card__text"></div></li>
  </ul>
  <p>Katso kaikki tapahtumat Tapahtumat-sivustolla</p>
</div>
HTML;
    $result = $this->getSut()->clean($this->parseHtml($html));

    $this->assertStringContainsString('Suositellut tapahtumat', $result);
    $this->assertStringContainsString('Katso kaikki tapahtumat', $result);
    $this->assertStringNotContainsString('<ul', $result);
    $this->assertStringNotContainsString('<li', $result);
  }

  /**
   * Tests that lists with real content survive intact.
   */
  public function testPreservesListsWithContent(): void {
    $html = '<ul><li>First</li><li>Second</li></ul>';
    $result = $this->getSut()->clean($this->parseHtml($html));

    $this->assertStringContainsString('First', $result);
    $this->assertStringContainsString('Second', $result);
    $this->assertSame(1, substr_count($result, '<ul'));
    $this->assertSame(2, substr_count($result, '<li'));
  }

}
