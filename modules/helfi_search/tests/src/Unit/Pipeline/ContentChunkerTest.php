<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_search\Unit\Pipeline;

use Drupal\helfi_search\Pipeline\Chunk;
use Drupal\helfi_search\Pipeline\ContentChunker;
use Drupal\helfi_search\Pipeline\Heading;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
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
   * Tests the short-chunk merge pass.
   *
   * @param array{string, string}[] $inputs
   *   List of [body, h2-heading-title] pairs for the input chunks.
   * @param array{string, string}[] $expected
   *   List of [h2-heading-title, body] pairs for the expected output chunks.
   */
  #[DataProvider('mergeCases')]
  public function testMerge(array $inputs, array $expected): void {
    $chunks = array_map(
      static fn (array $row) => new Chunk($row[0], heading: new Heading($row[1], 2)),
      $inputs,
    );

    $result = ContentChunker::mergeShortChunks($chunks);

    $this->assertCount(count($expected), $result);
    foreach ($expected as $i => [$expectedHeading, $expectedText]) {
      $this->assertSame($expectedHeading, $result[$i]->heading?->title);
      $this->assertSame($expectedText, $result[$i]->text);
    }
  }

  /**
   * Data provider for testMerge.
   *
   * @phpstan-return array<string, array<string, mixed>>
   */
  public static function mergeCases(): array {
    $long = str_repeat('Long content. ', 30);
    return [
      'two short chunks merge into one, second heading inlined' => [
        'inputs' => [
          ['Short body A.', 'Section A'],
          ['Short body B.', 'Section B'],
        ],
        'expected' => [
          ['Section A', "Short body A.\n\n## Section B\nShort body B."],
        ],
      ],
      'short then long passes through unchanged' => [
        'inputs' => [
          ['Short.', 'Section A'],
          [$long, 'Section B'],
        ],
        'expected' => [
          ['Section A', 'Short.'],
          ['Section B', $long],
        ],
      ],
      'long accumulator absorbs trailing short chunk' => [
        'inputs' => [
          [$long, 'Section A'],
          ['Short.', 'Section B'],
        ],
        'expected' => [
          ['Section A', "$long\n\n## Section B\nShort."],
        ],
      ],
      'same-section sub-chunks merge without duplicating the heading' => [
        'inputs' => [
          ['First half.', 'Same Section'],
          ['Second half.', 'Same Section'],
        ],
        'expected' => [
          ['Same Section', "First half.\n\nSecond half."],
        ],
      ],
    ];
  }

  /**
   * Tests flagHidden marks short non-first chunks as hidden.
   */
  public function testFlagHidden(): void {
    $long = str_repeat('Long content. ', 80);
    $chunks = [
      // First chunk is never hidden, even when short: it is the fallback.
      new Chunk('Short intro.'),
      new Chunk('Short section.'),
      new Chunk($long),
    ];

    $result = ContentChunker::flagHidden($chunks);

    $this->assertFalse($result[0]->hidden);
    $this->assertTrue($result[1]->hidden);
    $this->assertFalse($result[2]->hidden);
  }

  /**
   * Tests chunk() upholds the hidden invariant across a multi-section doc.
   *
   * The first chunk is never hidden; every other chunk is hidden exactly when
   * its final text is below the display threshold.
   */
  public function testChunkFlagsShortSections(): void {
    $markdown = implode("\n", [
      // Intro is long enough (>=800) to flush on its own, so the tiny section
      // that follows stays a standalone short chunk.
      str_repeat('Intro text. ', 80),
      '',
      '## Tiny section',
      '',
      'Just a sentence.',
      '',
      '## Big section',
      '',
      str_repeat('Content. ', 400),
    ]);

    $chunks = $this->getSut()->chunk($markdown);

    // More than one chunk, and at least one short section got hidden.
    $this->assertGreaterThan(1, count($chunks));
    $this->assertFalse($chunks[0]->hidden);
    $this->assertContains(TRUE, array_map(static fn (Chunk $c) => $c->hidden, $chunks));

    foreach ($chunks as $i => $chunk) {
      // Hidden iff not the first chunk and shorter than the display threshold.
      $expected = $i > 0 && mb_strlen($chunk->text) < 800;
      $this->assertSame($expected, $chunk->hidden);
    }
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

    $titles = array_map(fn(Chunk $c) => $c->heading?->title, $chunks);
    $this->assertContains('Subsection One', $titles);
    $this->assertContains('Section A', $titles);
    $this->assertContains('Section B', $titles);
  }

}
