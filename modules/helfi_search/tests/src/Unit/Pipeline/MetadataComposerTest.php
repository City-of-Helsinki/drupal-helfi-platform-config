<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_search\Unit\Pipeline;

use Drupal\Core\Entity\EntityInterface;
use Drupal\helfi_search\Pipeline\Chunk;
use Drupal\helfi_search\Pipeline\MetadataComposer;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the MetadataComposer pipeline service.
 */
#[Group('helfi_search')]
class MetadataComposerTest extends UnitTestCase {

  /**
   * Gets service under test.
   */
  private function getSut(): MetadataComposer {
    return new MetadataComposer();
  }

  /**
   * Creates a mock entity with the given label.
   */
  private function createEntity(): EntityInterface {
    return $this->createMock(EntityInterface::class);
  }

  /**
   * Tests chunk with parent chain produces breadcrumb heading.
   */
  public function testChunkWithParentChain(): void {
    $h1 = new Chunk('', context: ['title' => 'Services', 'level' => 1]);
    $h2 = new Chunk('', parent: $h1, context: ['title' => 'Foobar', 'level' => 2]);
    $h3 = new Chunk('FAQ body text.', parent: $h2, context: ['title' => 'FAQ', 'level' => 3]);

    $result = $this->getSut()->compose($this->createEntity(), [$h3]);

    $this->assertInstanceOf(Chunk::class, $result[0]);
    $this->assertSame("Services > Foobar\n---\nFAQ body text.", (string) $result[0]);
  }

  /**
   * Tests multiple chunks are all composed.
   */
  public function testMultipleChunks(): void {
    $chunk1 = new Chunk('First chunk.');
    $chunk2 = new Chunk('Second chunk.', context: ['title' => 'Section', 'level' => 2]);

    $result = $this->getSut()->compose($this->createEntity(), [$chunk1, $chunk2]);

    $this->assertCount(2, $result);
    $this->assertSame('First chunk.', (string) $result[0]);
    $this->assertSame('Second chunk.', (string) $result[1]);
  }

  /**
   * Tests that markdown lists render and inline emphasis is stripped to text.
   *
   * The whitelist intentionally excludes strong/em/code so the result card
   * has consistent typography; their content survives as plain text.
   */
  public function testSnippetRendersListsAndStripsInlineEmphasis(): void {
    $chunk = new Chunk("This is **bold** and *italic*.\n\n- one\n- two");

    $this->getSut()->compose($this->createEntity(), [$chunk]);

    $this->assertStringNotContainsString('<strong>', $chunk->snippet);
    $this->assertStringNotContainsString('<em>', $chunk->snippet);
    $this->assertStringContainsString('bold', $chunk->snippet);
    $this->assertStringContainsString('italic', $chunk->snippet);
    $this->assertStringContainsString('<ul>', $chunk->snippet);
    $this->assertStringContainsString('<li>one</li>', $chunk->snippet);
    $this->assertStringContainsString('<li>two</li>', $chunk->snippet);
  }

  /**
   * Tests that raw HTML embedded in the markdown input is stripped.
   */
  public function testSnippetStripsRawHtmlInMarkdownInput(): void {
    $chunk = new Chunk('Click <a href="https://evil.example">here</a> or <script>alert(1)</script>.');

    $this->getSut()->compose($this->createEntity(), [$chunk]);

    $this->assertStringNotContainsString('<script', $chunk->snippet);
    $this->assertStringNotContainsString('<a ', $chunk->snippet);
    $this->assertStringNotContainsString('href=', $chunk->snippet);
  }

  /**
   * Tests that links produced by markdown syntax are stripped, keeping text.
   */
  public function testSnippetStripsLinksProducedByMarkdown(): void {
    $chunk = new Chunk('Some [link text](https://example.com) inline.');

    $this->getSut()->compose($this->createEntity(), [$chunk]);

    $this->assertStringNotContainsString('<a ', $chunk->snippet);
    $this->assertStringNotContainsString('href', $chunk->snippet);
    $this->assertStringContainsString('link text', $chunk->snippet);
  }

  /**
   * Tests that all markdown headings are removed from the snippet.
   */
  public function testSnippetStripsAllHeadings(): void {
    $chunk = new Chunk("## Section heading\n\nIntro paragraph.\n\n### Deeper\n\nTail.");

    $this->getSut()->compose($this->createEntity(), [$chunk]);

    $this->assertStringContainsString('Intro paragraph', $chunk->snippet);
    $this->assertStringContainsString('Tail', $chunk->snippet);
    $this->assertStringNotContainsString('Section heading', $chunk->snippet);
    $this->assertStringNotContainsString('Deeper', $chunk->snippet);
    $this->assertStringNotContainsString('<h', $chunk->snippet);
  }

  /**
   * Tests that empty chunk text produces an empty snippet.
   */
  public function testSnippetEmptyForEmptyChunk(): void {
    $chunk = new Chunk('');
    $whitespace = new Chunk("   \n\n  ");

    $this->getSut()->compose($this->createEntity(), [$chunk, $whitespace]);

    $this->assertSame('', $chunk->snippet);
    $this->assertSame('', $whitespace->snippet);
  }

  /**
   * Tests that compose() populates snippet on every chunk.
   */
  public function testSnippetPopulatedOnEveryChunk(): void {
    $chunks = [
      new Chunk('First.'),
      new Chunk('Second.'),
      new Chunk('Third.'),
    ];

    $this->getSut()->compose($this->createEntity(), $chunks);

    foreach ($chunks as $chunk) {
      $this->assertNotNull($chunk->snippet);
      $this->assertStringStartsWith('<p>', $chunk->snippet);
    }
  }

}
