<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_search\Unit\Pipeline;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Language\Language;
use Drupal\helfi_search\Pipeline\Chunk;
use Drupal\helfi_search\Pipeline\MetadataComposer;
use Drupal\Tests\UnitTestCase;
use Masterminds\HTML5;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the MetadataComposer pipeline service.
 */
#[Group('helfi_search')]
class MetadataComposerTest extends UnitTestCase {

  /**
   * Tests chunk with parent chain produces breadcrumb heading.
   */
  public function testChunkWithParentChain(): void {
    $h1 = new Chunk('', context: ['title' => 'Services', 'level' => 1]);
    $h2 = new Chunk('', parent: $h1, context: ['title' => 'Foobar', 'level' => 2]);
    $h3 = new Chunk('FAQ body text.', parent: $h2, context: ['title' => 'FAQ', 'level' => 3]);

    $result = $this->getSut()->compose($this->createEntity(), [$h3], $this->createDocument());

    $this->assertInstanceOf(Chunk::class, $result[0]);
    $this->assertSame("Services > Foobar\n---\nFAQ body text.", (string) $result[0]);
  }

  /**
   * Tests an extracted fragment is set on the chunk as its own property.
   */
  public function testExtractedFragmentSetOnChunk(): void {
    $chunk = new Chunk('Body text.', context: ['title' => 'How to Apply', 'level' => 2]);
    $doc = $this->createDocument('<main class="layout-main-wrapper"><h2>How to Apply</h2></main>');

    $this->getSut()->compose($this->createEntity(), [$chunk], $doc);

    $this->assertSame('how-to-apply', $chunk->fragment);
    // Fragment lives only on the DTO; embedding text remains breadcrumb-only.
    $this->assertSame('Body text.', (string) $chunk);
  }

  /**
   * Tests sub-chunks of one section share the section's fragment.
   */
  public function testSubChunksOfSameSectionShareFragment(): void {
    $context = ['title' => 'How to Apply', 'level' => 2];
    $chunk1 = new Chunk('First half.', context: $context);
    $chunk2 = new Chunk('Second half.', context: $context);
    $doc = $this->createDocument('<main class="layout-main-wrapper"><h2>How to Apply</h2></main>');

    $this->getSut()->compose($this->createEntity(), [$chunk1, $chunk2], $doc);

    $this->assertSame('how-to-apply', $chunk1->fragment);
    $this->assertSame('how-to-apply', $chunk2->fragment);
  }

  /**
   * Tests h1 sections do not consume a fragment from the extractor list.
   */
  public function testH1DoesNotConsumeFragment(): void {
    $h1 = new Chunk('Top body.', context: ['title' => 'Top', 'level' => 1]);
    $h2 = new Chunk('Sub body.', context: ['title' => 'Sub', 'level' => 2]);
    $doc = $this->createDocument('<main class="layout-main-wrapper"><h2>Sub</h2></main>');

    $this->getSut()->compose($this->createEntity(), [$h1, $h2], $doc);

    $this->assertNull($h1->fragment);
    $this->assertSame('sub', $h2->fragment);
  }

  /**
   * Tests multiple chunks are all composed.
   */
  public function testMultipleChunks(): void {
    $chunk1 = new Chunk('First chunk.');
    $chunk2 = new Chunk('Second chunk.', context: ['title' => 'Section', 'level' => 2]);

    $result = $this->getSut()->compose($this->createEntity(), [$chunk1, $chunk2], $this->createDocument());

    $this->assertCount(2, $result);
    $this->assertSame('First chunk.', (string) $result[0]);
    $this->assertSame('Second chunk.', (string) $result[1]);
  }

  /**
   * MetadataComposer routes chunk text through SnippetRenderer.
   */
  public function testComposePopulatesSnippetViaRenderer(): void {
    $chunks = [
      new Chunk('Plain text body.'),
      new Chunk('**Bold** body with *emphasis*.'),
      new Chunk(''),
    ];

    $this->getSut()->compose($this->createEntity(), $chunks, $this->createDocument());

    $this->assertSame('Plain text body.', $chunks[0]->snippet);
    $this->assertSame('Bold body with emphasis.', $chunks[1]->snippet);
    $this->assertSame('', $chunks[2]->snippet);
  }

  /**
   * Parse a body fragment into a DOMDocument.
   */
  private function createDocument(string $bodyHtml = ''): \DOMDocument {
    $html5 = new HTML5(['disable_html_ns' => TRUE, 'encoding' => 'UTF-8']);
    return $html5->loadHTML('<!doctype html><html><body>' . $bodyHtml);
  }

  /**
   * Gets service under test.
   */
  private function getSut(): MetadataComposer {
    return new MetadataComposer();
  }

  /**
   * Mock entity that reports the given language code.
   */
  private function createEntity(string $langcode = 'en'): EntityInterface {
    $entity = $this->createMock(EntityInterface::class);
    $entity->method('language')->willReturn(new Language(['id' => $langcode]));
    return $entity;
  }

}
