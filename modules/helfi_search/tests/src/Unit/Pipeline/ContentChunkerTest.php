<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_search\Unit\Pipeline;

use Drupal\helfi_search\Pipeline\Chunk;
use Drupal\helfi_search\Pipeline\ContentChunker;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the ContentChunker pipeline service.
 */
#[Group('helfi_search')]
class ContentChunkerTest extends UnitTestCase {

  /**
   * Gets service under test.
   */
  private function getSut(): ContentChunker {
    return new ContentChunker();
  }

  /**
   * Tests that empty input returns a single empty chunk.
   */
  public function testEmptyInput(): void {
    $chunks = $this->getSut()->chunk('');

    $this->assertCount(1, $chunks);
    $this->assertInstanceOf(Chunk::class, $chunks[0]);
    $this->assertSame('', $chunks[0]->text);
    $this->assertNull($chunks[0]->parent);
  }

  /**
   * Tests that short content is returned as a single chunk.
   */
  public function testShortContentNotChunked(): void {
    $text = str_repeat('word ', 100);
    $chunks = $this->getSut()->chunk($text);

    $this->assertCount(1, $chunks);
    $this->assertSame(trim($text), trim($chunks[0]->text));
    $this->assertNull($chunks[0]->parent);
  }

  /**
   * Tests that large strings gets chunked.
   */
  public function testContentAtThresholdGetsChunked(): void {
    $text = str_repeat('a', 3000);
    $chunks = $this->getSut()->chunk($text);

    // Long strings are split regardless if they contain heading levels.
    $this->assertCount(2, $chunks);
  }

  /**
   * Tests heading-based splitting with headings.
   */
  public function testSplitsByHeadings(): void {
    $markdown = implode("\n", [
      str_repeat('Intro text. ', 50),
      '',
      '## Section A',
      '',
      str_repeat('Content A. ', 50),
      '',
      '### Subsection One',
      '',
      str_repeat('Sub content one. ', 100),
      '',
      '## Section B',
      '',
      str_repeat('Content B. ', 300),
    ]);

    $chunks = $this->getSut()->chunk($markdown);

    $this->assertEquals(5, count($chunks));

    $titles = array_column(array_map(fn(Chunk $c) => $c->context, $chunks), 'title');
    $this->assertContains('Subsection One', $titles);
    $this->assertContains('Section A', $titles);
    $this->assertContains('Section B', $titles);
  }

}
