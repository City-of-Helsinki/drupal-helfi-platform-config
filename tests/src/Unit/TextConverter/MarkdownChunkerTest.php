<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\TextConverter;

use Drupal\helfi_platform_config\TextConverter\MarkdownChunker;
use Drupal\Tests\UnitTestCase;

/**
 * Tests markdown chunker.
 *
 * @group helfi_recommendations
 */
class MarkdownChunkerTest extends UnitTestCase {

  private MarkdownChunker $sut;

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->sut = new MarkdownChunker();
  }

  /**
   * Tests basic h2 splitting with h1 context.
   */
  public function testBasicH2Splitting(): void {
    $markdown = implode("\n", [
      '# Main Title',
      'Intro text',
      '## Section A',
      'Content A',
      '## Section B',
      'Content B',
    ]);

    $chunks = $this->sut->chunk($markdown);

    $this->assertCount(3, $chunks);
    $this->assertEquals("# Main Title\nIntro text", $chunks[0]);
    $this->assertEquals("# Main Title\n## Section A\nContent A", $chunks[1]);
    $this->assertEquals("# Main Title\n## Section B\nContent B", $chunks[2]);
  }

  /**
   * Tests no headers returns single chunk.
   */
  public function testNoHeaders(): void {
    $markdown = "Just some plain text\nwith multiple lines.";

    $chunks = $this->sut->chunk($markdown);

    $this->assertCount(1, $chunks);
    $this->assertEquals($markdown, $chunks[0]);
  }

  /**
   * Tests splitting by h3.
   */
  public function testCustomHeaderLevel(): void {
    $markdown = implode("\n", [
      '# Title',
      '## Subtitle',
      'Intro',
      '### Section 1',
      'Content 1',
      '### Section 2',
      'Content 2',
    ]);

    $chunks = $this->sut->chunk($markdown, 3);

    $this->assertCount(3, $chunks);
    $this->assertEquals("# Title\n## Subtitle\nIntro", $chunks[0]);
    $this->assertEquals("# Title\n## Subtitle\n### Section 1\nContent 1", $chunks[1]);
    $this->assertEquals("# Title\n## Subtitle\n### Section 2\nContent 2", $chunks[2]);
  }

  /**
   * Tests additional context strings.
   */
  public function testAdditionalContext(): void {
    $markdown = implode("\n", [
      '# Main Title',
      'Intro text',
      '## Section A',
      'Content A',
      '## Section B',
      'Content B',
    ]);

    $chunks = $this->sut->chunk($markdown, 2, ['Type: news_article']);

    $this->assertCount(3, $chunks);
    $this->assertEquals("Type: news_article\n# Main Title\nIntro text", $chunks[0]);
    $this->assertEquals("Type: news_article\n# Main Title\n## Section A\nContent A", $chunks[1]);
    $this->assertEquals("Type: news_article\n# Main Title\n## Section B\nContent B", $chunks[2]);
  }

  /**
   * Tests multiple h1 headers updating context per section.
   */
  public function testMultipleH1Headers(): void {
    $markdown = implode("\n", [
      '# First Title',
      '## Section A',
      'Content A',
      '# Second Title',
      '## Section B',
      'Content B',
    ]);

    $chunks = $this->sut->chunk($markdown);

    $this->assertCount(3, $chunks);
    $this->assertEquals('# First Title', $chunks[0]);
    $this->assertEquals("# First Title\n## Section A\nContent A", $chunks[1]);
    $this->assertEquals("# Second Title\n## Section B\nContent B", $chunks[2]);
  }

  /**
   * Tests empty input.
   */
  public function testEmptyInput(): void {
    $chunks = $this->sut->chunk('');
    $this->assertCount(0, $chunks);
  }

  /**
   * Tests text with only context headers but no split-level headers.
   */
  public function testOnlyContextHeaders(): void {
    $markdown = implode("\n", [
      '# Title',
      'Some content',
      'More content',
    ]);

    $chunks = $this->sut->chunk($markdown);

    $this->assertCount(1, $chunks);
    $this->assertEquals($markdown, $chunks[0]);
  }

  /**
   * Tests no intro content before first split header.
   */
  public function testNoIntroContent(): void {
    $markdown = implode("\n", [
      '## Section A',
      'Content A',
      '## Section B',
      'Content B',
    ]);

    $chunks = $this->sut->chunk($markdown);

    $this->assertCount(2, $chunks);
    $this->assertEquals("## Section A\nContent A", $chunks[0]);
    $this->assertEquals("## Section B\nContent B", $chunks[1]);
  }

}
