<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_search\Unit\Pipeline;

use Drupal\helfi_search\Pipeline\Chunk;
use Drupal\helfi_search\Pipeline\Heading;
use Drupal\helfi_search\Pipeline\HeadingFragmentExtractor;
use Drupal\helfi_search\Pipeline\ChunkAnnotator;
use Drupal\Tests\UnitTestCase;
use Masterminds\HTML5;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the ChunkAnnotator pipeline service.
 */
#[Group('helfi_search')]
class ChunkAnnotatorTest extends UnitTestCase {

  /**
   * Tests chunk with parent chain produces breadcrumb heading.
   */
  public function testChunkWithParentChain(): void {
    $h1 = new Chunk('', heading: new Heading('Services', 1));
    $h2 = new Chunk('', parent: $h1, heading: new Heading('Foobar', 2));
    $h3 = new Chunk('FAQ body text.', parent: $h2, heading: new Heading('FAQ', 3));

    $result = $this->getSut()->annotate([$h3], $this->extractFragments());

    $this->assertInstanceOf(Chunk::class, $result[0]);
    $this->assertSame("# Services\n## Foobar\n### FAQ\nFAQ body text.", (string) $result[0]);
  }

  /**
   * Tests an extracted fragment is set on the chunk as its own property.
   */
  public function testExtractedFragmentSetOnChunk(): void {
    $chunk = new Chunk('Body text.', heading: new Heading('How to Apply', 2));
    $fragments = $this->extractFragments('<main class="layout-main-wrapper"><h2>How to Apply</h2></main>');

    $this->getSut()->annotate([$chunk], $fragments);

    $this->assertSame('how-to-apply', $chunk->fragment);
    // Fragment lives only on the DTO; embedding text remains breadcrumb-only.
    $this->assertSame("## How to Apply\nBody text.", (string) $chunk);
  }

  /**
   * Tests sub-chunks of one section share the section's fragment.
   */
  public function testSubChunksOfSameSectionShareFragment(): void {
    // Chunks are kept long enough to survive the short-chunk merge pass so
    // this test isolates fragment-sharing behavior.
    $heading = new Heading('How to Apply', 2);
    $chunk1 = new Chunk(str_repeat('First half content. ', 15), heading: $heading);
    $chunk2 = new Chunk(str_repeat('Second half content. ', 15), heading: $heading);
    $fragments = $this->extractFragments('<main class="layout-main-wrapper"><h2>How to Apply</h2></main>');

    $this->getSut()->annotate([$chunk1, $chunk2], $fragments);

    $this->assertSame('how-to-apply', $chunk1->fragment);
    $this->assertSame('how-to-apply', $chunk2->fragment);
  }

  /**
   * Tests h1 sections do not consume a fragment from the extractor list.
   */
  public function testH1DoesNotConsumeFragment(): void {
    // Padded past the merge threshold so each section stays a separate chunk.
    $h1 = new Chunk(str_repeat('Top body content. ', 15), heading: new Heading('Top', 1));
    $h2 = new Chunk(str_repeat('Sub body content. ', 15), heading: new Heading('Sub', 2));
    $fragments = $this->extractFragments('<main class="layout-main-wrapper"><h2>Sub</h2></main>');

    $this->getSut()->annotate([$h1, $h2], $fragments);

    $this->assertNull($h1->fragment);
    $this->assertSame('sub', $h2->fragment);
  }

  /**
   * Tests multiple chunks are all annotated.
   */
  public function testMultipleChunks(): void {
    // Chunks are padded past the short-chunk merge threshold so the test
    // covers multi-chunk rendering rather than the merge pass.
    $body1 = str_repeat('First chunk content. ', 15);
    $body2 = str_repeat('Second chunk content. ', 15);
    $chunk1 = new Chunk($body1);
    $chunk2 = new Chunk($body2, heading: new Heading('Section', 2));

    $result = $this->getSut()->annotate([$chunk1, $chunk2], $this->extractFragments());

    $this->assertCount(2, $result);
    $this->assertSame($body1, (string) $result[0]);
    $this->assertSame("## Section\n$body2", (string) $result[1]);
  }

  /**
   * ChunkAnnotator routes chunk text through SnippetRenderer.
   */
  public function testAnnotatePopulatesSnippetViaRenderer(): void {
    // Inputs are padded to exceed the short-chunk merge threshold so each
    // input chunk survives annotate() as its own output, isolating snippet
    // rendering behavior.
    $plain = str_repeat('Plain text body. ', 15);
    $marked = str_repeat('**Bold** body with *emphasis*. ', 15);
    $chunks = [
      new Chunk($plain),
      new Chunk($marked),
      new Chunk(''),
    ];

    $this->getSut()->annotate($chunks, $this->extractFragments());

    $this->assertSame(trim($plain), $chunks[0]->snippet);
    $this->assertSame(trim(str_replace(['**', '*'], '', $marked)), $chunks[1]->snippet);
    $this->assertSame('', $chunks[2]->snippet);
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

    $result = $this->getSut()->annotate($chunks, $this->extractFragments());

    $this->assertCount(count($expected), $result);
    foreach ($expected as $i => [$expectedHeading, $expectedText]) {
      $this->assertSame($expectedHeading, $result[$i]->heading?->title);
      $this->assertSame($expectedText, $result[$i]->text);
    }
  }

  /**
   * Data provider for testMerge.
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
      'long then short passes through unchanged' => [
        'inputs' => [
          [$long, 'Section A'],
          ['Short.', 'Section B'],
        ],
        'expected' => [
          ['Section A', $long],
          ['Section B', 'Short.'],
        ],
      ],
      'five short chunks split at cap of four' => [
        'inputs' => [
          ['Body A.', 'Section A'],
          ['Body B.', 'Section B'],
          ['Body C.', 'Section C'],
          ['Body D.', 'Section D'],
          ['Body E.', 'Section E'],
        ],
        'expected' => [
          [
            'Section A',
            "Body A.\n\n## Section B\nBody B.\n\n## Section C\nBody C.\n\n## Section D\nBody D.",
          ],
          ['Section E', 'Body E.'],
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
   * Tests the merged chunk takes the first section's fragment only.
   */
  public function testMergedChunkUsesFirstSectionFragment(): void {
    $a = new Chunk('Short body A.', heading: new Heading('How to Apply', 2));
    $b = new Chunk('Short body B.', heading: new Heading('Deadlines', 2));
    $fragments = $this->extractFragments(
      '<main class="layout-main-wrapper"><h2>How to Apply</h2><h2>Deadlines</h2></main>'
    );

    $result = $this->getSut()->annotate([$a, $b], $fragments);

    $this->assertCount(1, $result);
    $this->assertSame('how-to-apply', $result[0]->fragment);
  }

  /**
   * Parse a body fragment and return its heading fragment list.
   *
   * @return \Drupal\helfi_search\Pipeline\HeadingFragment[]
   *   Heading fragments extracted from the parsed body.
   */
  private function extractFragments(string $bodyHtml = ''): array {
    $html5 = new HTML5(['disable_html_ns' => TRUE, 'encoding' => 'UTF-8']);
    $doc = $html5->loadHTML('<!doctype html><html><body>' . $bodyHtml);
    return HeadingFragmentExtractor::extract($doc, 'en');
  }

  /**
   * Gets service under test.
   */
  private function getSut(): ChunkAnnotator {
    return new ChunkAnnotator();
  }

}
