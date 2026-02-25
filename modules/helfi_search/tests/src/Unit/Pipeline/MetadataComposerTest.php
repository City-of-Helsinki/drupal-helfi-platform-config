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

    $this->assertSame("Services > Foobar\n---\nFAQ body text.", $result[0]);
  }

  /**
   * Tests multiple chunks are all composed.
   */
  public function testMultipleChunks(): void {
    $chunk1 = new Chunk('First chunk.');
    $chunk2 = new Chunk('Second chunk.', context: ['title' => 'Section', 'level' => 2]);

    $result = $this->getSut()->compose($this->createEntity(), [$chunk1, $chunk2]);

    $this->assertCount(2, $result);
    $this->assertSame('First chunk.', $result[0]);
    $this->assertSame('Second chunk.', $result[1]);
  }

}
