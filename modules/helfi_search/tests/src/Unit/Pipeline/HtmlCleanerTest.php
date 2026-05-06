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
    return new HtmlCleaner($this->stubIgnoredClassesConfigFactory($ignoredClasses));
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

}
