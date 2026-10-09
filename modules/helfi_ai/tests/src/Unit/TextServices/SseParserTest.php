<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ai\Unit\TextServices;

use Drupal\helfi_ai\TextServices\SseParser;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests parsing server-sent events.
 */
#[Group('helfi_ai')]
#[CoversClass(SseParser::class)]
class SseParserTest extends UnitTestCase {

  /**
   * An event is returned once its terminating empty line has arrived.
   */
  public function testEventSplitAcrossChunks(): void {
    $parser = new SseParser();

    $this->assertSame([], $parser->feed("event: delta\nda"));
    $this->assertSame([], $parser->feed("ta: {\"text\": \"hei\"}\n"));
    $this->assertSame(
      [['event' => 'delta', 'data' => '{"text": "hei"}']],
      $parser->feed("\nevent: done\n"),
    );
  }

}
