<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit;

use Drupal\helfi_platform_config\HeadingIdInjector;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the HeadingIdInjector service.
 */
#[Group('helfi_platform_config')]
class HeadingIdInjectorTest extends UnitTestCase {

  /**
   * Tests heading injection inside the main wrapper.
   */
  #[DataProvider('injectData')]
  public function testInject(string $langcode, string $body, string $expected): void {
    $this->assertSame(
      $this->page($expected),
      $this->sut()->inject($this->page($body), $langcode),
    );
  }

  /**
   * Data provider for `testInject`.
   *
   * @phpstan-return array<string, array{string, string, string}>
   */
  public static function injectData(): array {
    return [
      // Headings get a slug and are made focusable.
      'ids and tabindex' => [
        'en',
        <<<HTML
        <h2>Visible</h2>
        <p>body</p>
        <h3>Foobar</h3>
        HTML,
        <<<HTML
        <h2 id="visible" tabindex="-1">Visible</h2>
        <p>body</p>
        <h3 id="foobar" tabindex="-1">Foobar</h3>
        HTML,
      ],

      // An existing id wins.
      'existing id is preserved' => [
        'en',
        <<<HTML
        <h2 id="custom-anchor">Pretty Title</h2>
        <h2>Pretty Title</h2>
        HTML,
        <<<HTML
        <h2 id="custom-anchor" tabindex="-1">Pretty Title</h2>
        <h2 id="pretty-title" tabindex="-1">Pretty Title</h2>
        HTML,
      ],

      'empty id attribute is treated as existing' => [
        'en',
        <<<HTML
        <h2 id="">Heading</h2>
        <h3>Second</h3>
        HTML,
        <<<HTML
        <h2 id="" tabindex="-1">Heading</h2>
        <h3 id="second" tabindex="-1">Second</h3>
        HTML,
      ],

      // Existing tabindex is left alone.
      'existing tabindex is not duplicated' => [
        'en',
        '<h2 tabindex="0">Focusable</h2>',
        '<h2 tabindex="0" id="focusable">Focusable</h2>',
      ],

      // An empty heading gets no id, but is still focusable.
      'empty heading gets no id' => [
        'en',
        '<h2></h2>',
        '<h2 tabindex="-1"></h2>',
      ],

      // A '>' inside an attribute value does not end the tag early. The
      // rewritten tag is re-serialized, so single quotes come back doubled.
      'angle bracket inside attribute value' => [
        'en',
        <<<HTML
        <h2 data-label="a > b">First</h2>
        <h2 data-label='c > d'>Second</h2>
        HTML,
        <<<HTML
        <h2 data-label="a > b" id="first" tabindex="-1">First</h2>
        <h2 data-label="c > d" id="second" tabindex="-1">Second</h2>
        HTML,
      ],

      // Ids elsewhere on the page are treated as reserved.
      'reserved ids are avoided' => [
        'en',
        <<<HTML
        <aside id="intro">sidebar</aside>
        <h2>Intro</h2>
        HTML,
        <<<HTML
        <aside id="intro">sidebar</aside>
        <h2 id="intro-1" tabindex="-1">Intro</h2>
        HTML,
      ],

      // Repeated heading text gets incrementing suffixes.
      'duplicate headings get incrementing suffixes' => [
        'en',
        <<<HTML
        <h2>Section</h2>
        <h2>Section</h2>
        <h2>Section</h2>
        HTML,
        <<<HTML
        <h2 id="section" tabindex="-1">Section</h2>
        <h2 id="section-2" tabindex="-1">Section</h2>
        <h2 id="section-3" tabindex="-1">Section</h2>
        HTML,
      ],

      // Hidden regions are skipped and consume no slug, so the visible
      // heading below is unsuffixed.
      'headings inside hidden region are skipped' => [
        'en',
        <<<HTML
        <div class="hide-from-table-of-contents">
          <h2>Section</h2>
        </div>
        <h2>Section</h2>
        HTML,
        <<<HTML
        <div class="hide-from-table-of-contents">
          <h2>Section</h2>
        </div>
        <h2 id="section" tabindex="-1">Section</h2>
        HTML,
      ],

      // See field--toc-enabled.html.twig. Maybe we could support noscript
      // table of contents now.
      'noscript headings are ignored' => [
        'en',
        <<<HTML
        <noscript class="table-of-contents__nojs js-remove">
          <h2 class="nojs__title">Content cannot be displayed</h2>
        </noscript>
        <h2>Content cannot be displayed</h2>
        HTML,
        <<<HTML
        <noscript class="table-of-contents__nojs js-remove">
          <h2 class="nojs__title">Content cannot be displayed</h2>
        </noscript>
        <h2 id="content-cannot-be-displayed" tabindex="-1">Content cannot be displayed</h2>
        HTML,
      ],

      // Headings in raw text and comments neither shift nor rewrite.
      'headings inside scripts and comments are ignored' => [
        'en',
        <<<HTML
        <script>var markup = '<h2>Not a heading</h2>';</script>
        <!-- <h2>Commented out</h2> -->
        <h2>Real</h2>
        HTML,
        <<<HTML
        <script>var markup = '<h2>Not a heading</h2>';</script>
        <!-- <h2>Commented out</h2> -->
        <h2 id="real" tabindex="-1">Real</h2>
        HTML,
      ],

      // The langcode is threaded through to the slugger: main languages use
      // the simple 'ä' to 'a' mapping.
      'main language transliteration' => [
        'fi',
        '<h2>Otsikko täällä</h2>',
        '<h2 id="otsikko-taalla" tabindex="-1">Otsikko täällä</h2>',
      ],

      // Other languages run the full transliteration table.
      'other language transliteration' => [
        'ru',
        '<h2>Привет</h2>',
        '<h2 id="privet" tabindex="-1">Привет</h2>',
      ],
    ];
  }

  /**
   * Tests that only the heading open tags are rewritten.
   */
  public function testNothingOutsideHeadingTagsIsRewritten(): void {
    $html = $this->page(<<<HTML
      <svg viewBox="0 0 24 24"><clipPath id="clip"></clipPath></svg>
      <h2 aria-label="">Heading</h2>
      <input type="text" value="">
      <source srcset="a.png">
      <js-placeholder token="abc123">
      <drupal-render-placeholder callback="x" arguments="a=&#039;quote&#039;" token="t"></drupal-render-placeholder>
      <p>a<div>b</div></p>
      <span>&nbsp;non-breaking</span>
    HTML);

    $expected = str_replace(
      '<h2 aria-label="">Heading</h2>',
      '<h2 aria-label="" id="heading" tabindex="-1">Heading</h2>',
      $html,
    );

    $this->assertSame($expected, $this->sut()->inject($html, 'en'));
  }

  /**
   * Constructs the system under test.
   */
  private function sut(): HeadingIdInjector {
    return new HeadingIdInjector();
  }

  /**
   * Wraps a snippet in a minimal page with the main wrapper.
   */
  private function page(string $body): string {
    return <<<HTML
      <!doctype html>
      <html lang="en">
        <head>
          <meta charset="utf-8">
        </head>
      <body>
      <main class="layout-main-wrapper">$body</main>
      </body>
      </html>
      HTML;
  }

}
