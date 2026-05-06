<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_search\Unit\Pipeline;

use Drupal\helfi_search\Pipeline\MarkdownConverter;
use Drupal\helfi_search\Pipeline\SnippetRenderer;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestWith;

/**
 * Tests for the SnippetRenderer pipeline helper.
 */
#[Group('helfi_search')]
class SnippetRendererTest extends UnitTestCase {

  /**
   * Tests blank or whitespace-only input produces an empty snippet.
   */
  #[TestWith([''])]
  #[TestWith(["   \n\n  "])]
  public function testReturnsEmptyForBlankInput(string $input): void {
    $this->assertSame('', SnippetRenderer::render($input));
  }

  /**
   * Tests runs of whitespace are collapsed to single spaces.
   */
  public function testCollapsesWhitespace(): void {
    $this->assertSame(
      'First paragraph. Second paragraph. Third.',
      SnippetRenderer::render("First paragraph.\n\n\nSecond     paragraph.\n\nThird."),
    );
  }

  /**
   * Tests long input is word-safe truncated to the length cap with ellipsis.
   */
  public function testTruncatesAtCharacterLimit(): void {
    $body = str_repeat('Lorem ipsum dolor sit amet consectetur adipiscing elit. ', 20);
    $snippet = SnippetRenderer::render($body);

    $this->assertLessThanOrEqual(200, mb_strlen($snippet));
    $this->assertStringEndsWith('…', $snippet);
  }

  /**
   * Tests Markdown headings of any level are removed from the snippet.
   *
   * Top-level heading lines (column 0) are dropped entirely because the
   * result card already renders the chunk's heading. Headings exposed by
   * peeling a list or blockquote container have only their "#" marker
   * dropped — the inline text survives, since those represent body
   * content rather than a duplicate of the card heading. Mid-line "#" and
   * "#tag" (no space) are not headings and must survive.
   */
  #[TestWith(["# Heading\nBody text.", 'Body text.'])]
  #[TestWith(["## Sub\nBody text.", 'Body text.'])]
  #[TestWith(["###### Smallest\nBody text.", 'Body text.'])]
  #[TestWith(['# Only a heading', ''])]
  #[TestWith(["# First\n## Second\nBody text.", 'Body text.'])]
  #[TestWith(["- # List\n- # Of Headings", 'List Of Headings'])]
  #[TestWith(["1. # First\n2. # Second", 'First Second'])]
  #[TestWith(["- text\n- # heading\n- more", 'text heading more'])]
  #[TestWith(["- outer\n  - # nested", 'outer nested'])]
  #[TestWith(["# Stuff\n- # List\n- # Of headings", 'List Of headings'])]
  #[TestWith(['> # quoted heading', 'quoted heading'])]
  #[TestWith(['- > # deep', 'deep'])]
  #[TestWith(['- # ## still a heading', 'still a heading'])]
  #[TestWith(['Mid-line # is not a heading.', 'Mid-line # is not a heading.'])]
  #[TestWith(['#tag without space stays', '#tag without space stays'])]
  public function testStripsHeadings(string $input, string $expected): void {
    $this->assertSame($expected, SnippetRenderer::render($input));
  }

  /**
   * Tests list items holding inline Markdown flatten to plain text.
   *
   * Lists can contain other markdown elements (emphasis, code spans,
   * escaped punctuation). Stripping the list marker must leave their
   * normal handlers free to unwrap the inner content.
   */
  #[TestWith(["- **Bold** item\n- *Italic* item", 'Bold item Italic item'])]
  #[TestWith(["- use `foo()` here\n- and `bar()` there", 'use foo() here and bar() there'])]
  #[TestWith(["1. **First**\n2. *Second*\n3. `Third`", 'First Second Third'])]
  #[TestWith(["- # **Bold** heading\n- # `code` heading", 'Bold heading code heading'])]
  #[TestWith(['- \*literal\* in a list', '*literal* in a list'])]
  public function testStripsListItemsContainingInlineMarkdown(string $input, string $expected): void {
    $this->assertSame($expected, SnippetRenderer::render($input));
  }

  /**
   * Tests bold and italic markers are stripped to plain text.
   *
   * MarkdownConverter is configured to emit "**" for bold and "*" for italic.
   * Multiplication ("5 * 3") survives the emphasis pass.
   */
  #[TestWith(['Hello **bold** world.', 'Hello bold world.'])]
  #[TestWith(['Hello *italic* world.', 'Hello italic world.'])]
  #[TestWith(['5 * 3 = 15', '5 * 3 = 15'])]
  public function testStripsEmphasisMarkers(string $input, string $expected): void {
    $this->assertSame($expected, SnippetRenderer::render($input));
  }

  /**
   * Tests list and blockquote HTML flattens correctly.
   */
  #[TestWith(['<ul><li>a</li><li>b</li><li>c</li></ul>', 'a b c'])]
  #[TestWith(['<ol><li>a</li><li>b</li><li>c</li></ol>', 'a b c'])]
  #[TestWith(['<ul><li>a<ul><li>b</li></ul></li></ul>', 'a b'])]
  #[TestWith(['<blockquote><p>quoted line</p></blockquote>', 'quoted line'])]
  #[TestWith(['<blockquote><blockquote><p>deep</p></blockquote></blockquote>', 'deep'])]
  public function testStripsListAndBlockquoteMarkers(string $html, string $expected): void {
    $markdown = MarkdownConverter::convert($html);
    $this->assertSame($expected, SnippetRenderer::render($markdown));
  }

  /**
   * Tests inline code spans and backtick-fenced code blocks are unwrapped.
   */
  #[TestWith(['use `foo()` here', 'use foo() here'])]
  #[TestWith(["```js\nconsole.log()\n```", 'console.log()'])]
  public function testStripsCode(string $input, string $expected): void {
    $this->assertSame($expected, SnippetRenderer::render($input));
  }

  /**
   * Tests "---" horizontal-rule lines are dropped.
   */
  public function testStripsHorizontalRules(): void {
    $this->assertSame(
      'Above Below',
      SnippetRenderer::render("Above\n\n---\n\nBelow"),
    );
  }

  /**
   * Tests backslash-escaped markdown punctuation is restored to literal form.
   */
  #[TestWith(['\*literal\*', '*literal*'])]
  #[TestWith(['\\\\backslash', '\backslash'])]
  public function testUnescapesMarkdownPunctuation(string $input, string $expected): void {
    $this->assertSame($expected, SnippetRenderer::render($input));
  }

  /**
   * Tests inline HTML and HTML entities are normalized.
   */
  #[TestWith(['<span class="x">hello</span>', 'hello'])]
  #[TestWith(['Tom &amp; Jerry&#8217;s', "Tom & Jerry\u{2019}s"])]
  public function testStripsHtmlAndDecodesEntities(string $input, string $expected): void {
    $this->assertSame($expected, SnippetRenderer::render($input));
  }

  /**
   * Tests a raw <a> tag is unwrapped to its visible text only.
   */
  public function testStripsRawAnchorTag(): void {
    $snippet = SnippetRenderer::render('Click <a href="https://evil.example">here</a>.');
    $this->assertStringContainsString('Click here.', $snippet);
    $this->assertStringNotContainsString('<', $snippet);
    $this->assertStringNotContainsString('href', $snippet);
    $this->assertStringNotContainsString('evil.example', $snippet);
  }

  /**
   * Regression test: mix of supported syntax flattens cleanly.
   */
  public function testCombinesAllSyntaxIntoCleanText(): void {
    $input = <<<'MD'
**Bold** intro with *italic* and `inline code`.

> A blockquote line.

- First item
- Second item

---

End of body.
MD;

    $this->assertSame(
      'Bold intro with italic and inline code. A blockquote line. First item Second item End of body.',
      SnippetRenderer::render($input),
    );
  }

}
