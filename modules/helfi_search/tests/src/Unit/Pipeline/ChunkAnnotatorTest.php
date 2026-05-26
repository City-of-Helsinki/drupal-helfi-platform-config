<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_search\Unit\Pipeline;

use Drupal\helfi_search\Pipeline\Chunk;
use Drupal\helfi_search\Pipeline\Heading;
use Drupal\helfi_search\Pipeline\HeadingFragmentExtractor;
use Drupal\helfi_search\Pipeline\ChunkAnnotator;
use Drupal\Tests\UnitTestCase;
use Masterminds\HTML5;
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
    $heading = new Heading('How to Apply', 2);
    $chunk1 = new Chunk('First half content.', heading: $heading);
    $chunk2 = new Chunk('Second half content.', heading: $heading);
    $fragments = $this->extractFragments('<main class="layout-main-wrapper"><h2>How to Apply</h2></main>');

    $this->getSut()->annotate([$chunk1, $chunk2], $fragments);

    $this->assertSame('how-to-apply', $chunk1->fragment);
    $this->assertSame('how-to-apply', $chunk2->fragment);
  }

  /**
   * Tests h1 sections do not consume a fragment from the extractor list.
   */
  public function testH1DoesNotConsumeFragment(): void {
    $h1 = new Chunk('Top body content.', heading: new Heading('Top', 1));
    $h2 = new Chunk('Sub body content.', heading: new Heading('Sub', 2));
    $fragments = $this->extractFragments('<main class="layout-main-wrapper"><h2>Sub</h2></main>');

    $this->getSut()->annotate([$h1, $h2], $fragments);

    $this->assertNull($h1->fragment);
    $this->assertSame('sub', $h2->fragment);
  }

  /**
   * Tests multiple chunks are all annotated.
   */
  public function testMultipleChunks(): void {
    $body1 = 'First chunk content.';
    $body2 = 'Second chunk content.';
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
    $plain = 'Plain text body.';
    $marked = '**Bold** body with *emphasis*.';
    $chunks = [
      new Chunk($plain),
      new Chunk($marked),
      new Chunk(''),
    ];

    $this->getSut()->annotate($chunks, $this->extractFragments());

    $this->assertSame($plain, $chunks[0]->snippet);
    $this->assertSame(str_replace(['**', '*'], '', $marked), $chunks[1]->snippet);
    $this->assertSame('', $chunks[2]->snippet);
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
